<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Exception\MethodNotImplementedException;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Store\Concept\SqliteStore;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use ReflectionClass;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * Tracks the state of entities synced to and from third-party backends in a
 * local SQLite database
 *
 */
final class SyncStore extends SqliteStore
{
    /**
     * @var int|null
     */
    private $RunId;

    /**
     * @var string|null
     */
    private $RunUuid;

    /**
     * Provider ID => provider
     *
     * @var array<int,ISyncProvider>
     */
    private $Providers = [];

    /**
     * Provider hash => provider
     *
     * @var array<string,ISyncProvider>
     */
    private $ProvidersByHash = [];

    /**
     * Entity class => entity type ID
     *
     * @var array<string,int>
     */
    private $EntityTypes = [];

    /**
     * Prefix => PHP namespace
     *
     * @var array<string,string>|null
     */
    private $NamespacesByPrefix;

    /**
     * Prefix => namespace base URI
     *
     * @var array<string,string>|null
     */
    private $NamespaceUrisByPrefix;

    /**
     * @var SyncErrorCollection
     */
    private $Errors;

    /**
     * @var int
     */
    private $ErrorCount = 0;

    /**
     * @var int
     */
    private $WarningCount = 0;

    /**
     * @var bool
     */
    private $IsLoaded = false;

    /**
     * @var string|null
     */
    private $Command;

    /**
     * @var string[]|null
     */
    private $Arguments;

    /**
     * Prefix => [ namespace base URI, PHP namespace ]
     *
     * @var array<string,string[]>|null
     */
    private $Namespaces = [];

    /**
     * Initiate a "run" of sync operations
     *
     * @param string $command The canonical name of the command performing sync
     * operations (e.g. a qualified class and/or method name).
     * @param string[] $arguments Arguments passed to the command.
     */
    public function __construct(string $filename = ':memory:', string $command = '', array $arguments = [])
    {
        $this->requireUpsert();

        $this->Errors    = new SyncErrorCollection();
        $this->Command   = $command;
        $this->Arguments = $arguments;

        $this->open($filename);
    }

    /**
     * Create or open a sync entity database
     *
     * @return $this
     */
    private function open(string $filename)
    {
        $this->openDb($filename);
        $this->db()->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS
              _sync_run (
                run_id INTEGER NOT NULL PRIMARY KEY,
                run_uuid BLOB NOT NULL UNIQUE,
                run_command TEXT NOT NULL,
                run_arguments_json TEXT NOT NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                finished_at DATETIME,
                exit_status INTEGER,
                error_count INTEGER,
                warning_count INTEGER,
                errors_json TEXT
              );

            CREATE TABLE IF NOT EXISTS
              _sync_provider (
                provider_id INTEGER NOT NULL PRIMARY KEY,
                provider_hash BLOB NOT NULL UNIQUE,
                provider_class TEXT NOT NULL,
                added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
              );

            CREATE TABLE IF NOT EXISTS
              _sync_entity_type (
                entity_type_id INTEGER NOT NULL PRIMARY KEY,
                entity_type_class TEXT NOT NULL UNIQUE,
                added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
              );

            CREATE TABLE IF NOT EXISTS
              _sync_entity_type_state (
                provider_id INTEGER NOT NULL,
                entity_type_id INTEGER NOT NULL,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_sync DATETIME,
                PRIMARY KEY (provider_id, entity_type_id),
                FOREIGN KEY (provider_id) REFERENCES _sync_provider,
                FOREIGN KEY (entity_type_id) REFERENCES _sync_entity_type
              );

            CREATE TABLE IF NOT EXISTS
              _sync_entity (
                provider_id INTEGER NOT NULL,
                entity_type_id INTEGER NOT NULL,
                entity_id TEXT NOT NULL,
                canonical_id TEXT,
                is_dirty INTEGER NOT NULL DEFAULT 0,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_sync DATETIME,
                entity_json TEXT NOT NULL,
                PRIMARY KEY (provider_id, entity_type_id, entity_id),
                FOREIGN KEY (provider_id) REFERENCES _sync_provider,
                FOREIGN KEY (entity_type_id) REFERENCES _sync_entity_type
              ) WITHOUT ROWID;

            CREATE TABLE IF NOT EXISTS
              _sync_entity_namespace (
                entity_namespace_id INTEGER NOT NULL PRIMARY KEY,
                entity_namespace_prefix TEXT NOT NULL UNIQUE,
                base_uri TEXT NOT NULL,
                php_namespace TEXT NOT NULL,
                added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
              );

            SQL
        );
        $this->IsLoaded = true;

        return $this;
    }

    public function close(?int $exitStatus = 0)
    {
        if (!$this->isOpen()) {
            return $this;
        }

        // Don't start a run now
        if (is_null($this->RunId)) {
            return parent::close();
        }

        $db  = $this->db();
        $sql = <<<SQL
        UPDATE
          _sync_run
        SET
          finished_at = CURRENT_TIMESTAMP,
          exit_status = :exit_status,
          error_count = :error_count,
          warning_count = :warning_count,
          errors_json = :errors_json
        WHERE
          run_uuid = :run_uuid;
        SQL;

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':exit_status', $exitStatus, SQLITE3_INTEGER);
        $stmt->bindValue(':run_uuid', $this->RunUuid, SQLITE3_BLOB);
        $stmt->bindValue(':error_count', $this->ErrorCount, SQLITE3_INTEGER);
        $stmt->bindValue(':warning_count', $this->WarningCount, SQLITE3_INTEGER);
        $stmt->bindValue(':errors_json', json_encode($this->Errors), SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        return parent::close();
    }

    /**
     * Get the run ID of the current run
     *
     */
    public function getRunId(): int
    {
        $this->check();

        return $this->RunId;
    }

    /**
     * Get the UUID of the current run
     *
     * @param bool $binary If `true`, return 16 bytes of raw binary data,
     * otherwise return a 36-byte hexadecimal representation.
     */
    public function getRunUuid(bool $binary = false): string
    {
        $this->check();

        return $binary ? $this->RunUuid : Convert::uuidToHex($this->RunUuid);
    }

    /**
     * Register a sync provider and set its provider ID
     *
     * @return $this
     */
    public function provider(ISyncProvider $provider)
    {
        $class = get_class($provider);
        $hash  = Compute::binaryHash($class, ...$provider->getBackendIdentifier());

        if (!is_null($this->ProvidersByHash[$hash] ?? null)) {
            throw new RuntimeException("Provider already registered: $class");
        }

        // Update `last_seen` if the provider is already in the database
        $db  = $this->db();
        $sql = <<<SQL
        INSERT INTO
          _sync_provider (provider_hash, provider_class)
        VALUES
          (:provider_hash, :provider_class) ON CONFLICT (provider_hash) DO
        UPDATE
        SET
          last_seen = CURRENT_TIMESTAMP;
        SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':provider_hash', $hash, SQLITE3_BLOB);
        $stmt->bindValue(':provider_class', $class, SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $sql = <<<SQL
        SELECT
          provider_id
        FROM
          _sync_provider
        WHERE
          provider_hash = :provider_hash;
        SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':provider_hash', $hash, SQLITE3_BLOB);
        $result = $stmt->execute();
        $row    = $result->fetchArray(SQLITE3_NUM);
        $stmt->close();

        if ($row === false) {
            throw new RuntimeException('Error retrieving provider ID');
        }

        $provider->setProviderId($row[0], $hash);
        $this->Providers[$row[0]] = $this->ProvidersByHash[$hash] = $provider;

        return $this;
    }

    /**
     * Register a sync entity type and set its ID unless already registered
     *
     * @return $this
     */
    public function entityType(string $entity)
    {
        if (!is_null($this->EntityTypes[$entity] ?? null)) {
            return $this;
        }

        $class = new ReflectionClass($entity);
        if (!$class->isSubclassOf(SyncEntity::class)) {
            throw new UnexpectedValueException("Not a subclass of SyncEntity: $entity");
        }

        // Update `last_seen` if the entity type is already in the database
        $db  = $this->db();
        $sql = <<<SQL
        INSERT INTO
          _sync_entity_type (entity_type_class)
        VALUES
          (:entity_type_class) ON CONFLICT (entity_type_class) DO
        UPDATE
        SET
          last_seen = CURRENT_TIMESTAMP;
        SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':entity_type_class', $class->name, SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $sql = <<<SQL
        SELECT
          entity_type_id
        FROM
          _sync_entity_type
        WHERE
          entity_type_class = :entity_type_class;
        SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':entity_type_class', $class->name, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row    = $result->fetchArray(SQLITE3_NUM);
        $stmt->close();

        if ($row === false) {
            throw new RuntimeException('Error retrieving entity type ID');
        }

        $class->getMethod('setEntityTypeId')->invoke(null, $row[0]);
        $this->EntityTypes[$entity] = $row[0];

        return $this;
    }

    /**
     * Register a sync entity namespace
     *
     * @return $this
     */
    public function namespace(string $prefix, string $uri, string $namespace)
    {
        // Don't start a run just to register a namespace
        if (is_null($this->RunId)) {
            $this->Namespaces[$prefix] = [$uri, $namespace];

            return $this;
        }

        // Update `last_seen` if the namespace is already in the database
        $db  = $this->db();
        $sql = <<<SQL
        INSERT INTO
          _sync_entity_namespace (entity_namespace_prefix, base_uri, php_namespace)
        VALUES
          (
            :entity_namespace_prefix,
            :base_uri,
            :php_namespace
          ) ON CONFLICT (entity_namespace_prefix) DO
        UPDATE
        SET
          base_uri = excluded.base_uri,
          php_namespace = excluded.php_namespace,
          last_seen = CURRENT_TIMESTAMP;
        SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':entity_namespace_prefix', $prefix, SQLITE3_TEXT);
        $stmt->bindValue(':base_uri', rtrim($uri, '/') . '/', SQLITE3_TEXT);
        $stmt->bindValue(':php_namespace', trim($namespace, '\\') . '\\', SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        // Don't reload while bootstrapping
        if (is_null($this->NamespacesByPrefix)) {
            return $this;
        }

        return $this->reload();
    }

    /**
     * Get the canonical URI of a sync entity type
     *
     * @return string|null `null` if `$entity` is not in a registered sync
     * entity namespace.
     * @see SyncStore::namespace()
     */
    public function getEntityTypeUri(string $entity, bool $compact = true): ?string
    {
        if (!($prefix = $this->getEntityTypeNamespace($entity))) {
            return null;
        }
        $namespace = $this->NamespacesByPrefix[$prefix];
        $entity    = str_replace('\\', '/', substr(ltrim($entity, '\\'), strlen($namespace)));

        return $compact
            ? "{$prefix}:{$entity}"
            : "{$this->NamespaceUrisByPrefix[$prefix]}{$entity}";
    }

    /**
     * Get the namespace of a sync entity type
     *
     * @return string|null `null` if `$entity` is not in a registered sync
     * entity namespace.
     * @see SyncStore::namespace()
     */
    public function getEntityTypeNamespace(string $entity, bool $uri = false): ?string
    {
        if (!is_a($entity, SyncEntity::class, true)) {
            throw new UnexpectedValueException("Not a subclass of SyncEntity: $entity");
        }

        $this->check();

        $entity = ltrim($entity, '\\');
        $lower  = strtolower($entity);
        foreach ($this->NamespacesByPrefix as $prefix => $namespace) {
            if (strpos($lower, $namespace) === 0) {
                return $uri
                    ? $this->NamespaceUrisByPrefix[$prefix]
                    : $prefix;
            }
        }

        return null;
    }

    /**
     * Throw an exception if a registered provider has an unreachable backend
     *
     * @return $this
     */
    public function checkHeartbeats()
    {
        foreach ($this->Providers as $id => $provider) {
            $name = ($provider->name() ?: get_class($provider)) . " [#$id]";
            Console::logProgress('Checking', $name . "\r");
            try {
                $provider->checkHeartbeat();
                Console::log('Heartbeat OK:', $name);
            } catch (MethodNotImplementedException $ex) {
                Console::log('Heartbeat check not supported:', $name);
            } catch (Throwable $ex) {
                Console::log('No heartbeat:', $name);
                throw $ex;
            }
        }

        return $this;
    }

    /**
     * Report an error that occurred during a sync operation
     *
     * @param SyncError|SyncErrorBuilder $error
     * @return $this
     */
    public function error($error, bool $deduplicate = false, bool $toConsole = false)
    {
        /** @var SyncError $error */
        $error = SyncErrorBuilder::resolve($error);
        if (!$deduplicate || !($seen = $this->Errors->get($error))) {
            $this->Errors[] = $error;

            switch ($error->Level) {
                case Level::EMERGENCY:
                case Level::ALERT:
                case Level::CRITICAL:
                case Level::ERROR:
                    $this->ErrorCount++;
                    break;
                case Level::WARNING:
                    $this->WarningCount++;
                    break;
            }
        } else {
            /** @var SyncError $seen */
            $seen->count();
        }

        if ($toConsole) {
            $error->toConsole($deduplicate);
        } else {
            Console::count($error->Level);
        }

        return $this;
    }

    public function getErrors(): SyncErrorCollection
    {
        return clone $this->Errors;
    }

    protected function check(): void
    {
        // Don't check anything until `open()` returns, otherwise tables etc.
        // won't be created because the query below will fail, and every
        // invocation will initiate a run, whether sync is used or not
        if (!$this->IsLoaded || !is_null($this->RunId)) {
            return;
        }

        $sql = <<<SQL
        INSERT INTO _sync_run (run_uuid, run_command, run_arguments_json)
        VALUES (
            :run_uuid,
            :run_command,
            :run_arguments_json
          );
        SQL;

        $db   = $this->db(true);
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':run_uuid', $uuid = Compute::uuid(true), SQLITE3_BLOB);
        $stmt->bindValue(':run_command', $this->Command, SQLITE3_TEXT);
        $stmt->bindValue(':run_arguments_json', json_encode($this->Arguments), SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $id            = $db->lastInsertRowID();
        $this->RunId   = $id;
        $this->RunUuid = $uuid;
        unset($this->Command, $this->Arguments);

        foreach ($this->Namespaces as $prefix => [$uri, $namespace]) {
            $this->namespace($prefix, $uri, $namespace);
        }
        unset($this->Namespaces);

        $this->reload();
    }

    /**
     * @return $this
     */
    private function reload()
    {
        $db  = $this->db();
        $sql = <<<SQL
        SELECT
          entity_namespace_prefix,
          base_uri,
          php_namespace
        FROM
          _sync_entity_namespace
        ORDER BY
          LENGTH(php_namespace) DESC;
        SQL;
        $stmt                        = $db->prepare($sql);
        $result                      = $stmt->execute();
        $this->NamespacesByPrefix    = [];
        $this->NamespaceUrisByPrefix = [];
        while (($row = $result->fetchArray(SQLITE3_NUM)) !== false) {
            $this->NamespacesByPrefix[$row[0]]    = strtolower($row[2]);
            $this->NamespaceUrisByPrefix[$row[0]] = $row[1];
        }
        $result->finalize();
        $stmt->close();

        return $this;
    }

    public function __destruct()
    {
        // If not closed explicitly, assume something went wrong
        $this->close(1);
    }
}

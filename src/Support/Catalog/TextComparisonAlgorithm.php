<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\Enumeration;
use Lkrms\Utility\Compute;

/**
 * Text comparison algorithms
 *
 * Text comparison algorithms take two strings and calculate a value between `0`
 * and `1`, where `0` indicates the strings could not be more similar, and `1`
 * indicates they could not be more different.
 *
 * @extends Enumeration<int>
 */
class TextComparisonAlgorithm extends Enumeration
{
    /**
     * Uncertainty is 0 if values are identical, otherwise 1
     */
    public const SAME = 1;

    /**
     * Uncertainty is 0 if the longer value contains the shorter value,
     * otherwise 1
     */
    public const CONTAINS = 2;

    /**
     * Uncertainty is derived from levenshtein()
     *
     * String length cannot exceed 255 characters.
     *
     * @see levenshtein()
     */
    public const LEVENSHTEIN = 4;

    /**
     * Uncertainty is derived from similar_text()
     *
     * @see similar_text()
     */
    public const SIMILAR_TEXT = 8;

    /**
     * Uncertainty is derived from ngramSimilarity()
     *
     * @see Compute::ngramSimilarity()
     */
    public const NGRAM_SIMILARITY = 16;

    /**
     * Uncertainty is derived from ngramIntersection()
     *
     * @see Compute::ngramIntersection()
     */
    public const NGRAM_INTERSECTION = 32;
}

<?php

declare(strict_types=1);

namespace EzPhp\BigNum;

/**
 * Rounding mode enum for BigDecimal operations.
 *
 * Determines how to handle the discarded digits when scaling down a value.
 */
enum RoundingMode
{
    /** Round away from zero. */
    case UP;

    /** Round towards zero (truncate). */
    case DOWN;

    /** Round towards positive infinity. */
    case CEILING;

    /** Round towards negative infinity. */
    case FLOOR;

    /** Round half away from zero (standard rounding). */
    case HALF_UP;

    /** Round half towards zero. */
    case HALF_DOWN;

    /** Round half to the nearest even digit (banker's rounding). */
    case HALF_EVEN;
}

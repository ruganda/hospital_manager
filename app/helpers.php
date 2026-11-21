<?php

if (! function_exists('money')) {
    /** Format an amount in the clinic currency, e.g. "UGX 45,000". */
    function money(float|int|string|null $amount): string
    {
        return config('clinic.currency').' '.number_format((float) $amount, config('clinic.currency_decimals', 0));
    }
}

if (! function_exists('money_plain')) {
    /** Number only (for Excel/CSV cells), e.g. "45000". */
    function money_plain(float|int|string|null $amount): string
    {
        return number_format((float) $amount, config('clinic.currency_decimals', 0), '.', '');
    }
}

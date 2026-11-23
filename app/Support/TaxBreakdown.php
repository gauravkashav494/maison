<?php

namespace App\Support;

/**
 * Tax on a set of lines that may each carry a different rate (each product's tax).
 *
 * A discount on the whole order is shared across the lines by value, so each line is taxed on
 * what is actually paid for it. With prices that include tax, a line's tax is the part of it
 * that is tax and nothing is added; with prices before tax, the tax is added on top.
 */
class TaxBreakdown
{
    /**
     * @param  list<array{amount: float, rate: float}>  $lines  each line's total and tax rate
     * @return array{
     *     lines: list<array{taxable: float, tax: float}>,
     *     rates: list<array{rate: float, taxable: float, tax: float}>,
     *     tax: float,
     *     rate: ?float
     * } per line, then per rate (lowest first; taxable = value before tax), the total tax, and
     *   the one rate every line shares (null when they differ or there are none)
     */
    public static function for(array $lines, float $discount, bool $inclusive): array
    {
        $subtotal = array_sum(array_column($lines, 'amount'));
        $discount = min(max(0, $discount), $subtotal);

        // Each line's share of the discount; the last line takes what rounding leaves.
        $shares = [];
        $left = round($discount, 2);
        $last = array_key_last($lines);
        foreach ($lines as $i => $line) {
            $shares[$i] = $i === $last || $subtotal <= 0
                ? $left
                : round($discount * $line['amount'] / $subtotal, 2);
            $left = round($left - $shares[$i], 2);
        }

        $perLine = [];
        $byRate = [];
        foreach ($lines as $i => $line) {
            $rate = round(max(0, (float) $line['rate']), 2);
            $paid = max(0, round($line['amount'] - $shares[$i], 2));
            $tax = match (true) {
                $rate <= 0 => 0.0,
                $inclusive => round($paid - $paid / (1 + $rate / 100), 2),
                default => round($paid * $rate / 100, 2),
            };
            $taxable = $inclusive ? round($paid - $tax, 2) : $paid;
            $perLine[$i] = ['taxable' => $taxable, 'tax' => $tax];

            $key = number_format($rate, 2, '.', '');
            $byRate[$key] ??= ['rate' => $rate, 'taxable' => 0.0, 'tax' => 0.0];
            $byRate[$key]['taxable'] = round($byRate[$key]['taxable'] + $taxable, 2);
            $byRate[$key]['tax'] = round($byRate[$key]['tax'] + $tax, 2);
        }
        ksort($byRate, SORT_NUMERIC);

        return [
            'lines' => $perLine,
            'rates' => array_values($byRate),
            'tax' => round(array_sum(array_column($perLine, 'tax')), 2),
            'rate' => count($byRate) === 1 ? reset($byRate)['rate'] : null,
        ];
    }

    /**
     * Tax rows for a cart, order or invoice: one per rate that carries tax. "CGST + SGST" is one
     * tax split evenly between the centre and the state, so each rate gives a CGST and an SGST row.
     *
     * @param  list<array{rate: float, taxable: float, tax: float}>  $rates
     * @return list<array{label: string, rate: float, amount: float, taxable: float}>
     */
    public static function rows(array $rates, string $label): array
    {
        $rows = [];
        foreach ($rates as $r) {
            if ($r['tax'] <= 0) {
                continue;
            }
            if ($label === 'CGST + SGST') {
                $half = round($r['tax'] / 2, 2);
                $rows[] = ['label' => 'CGST', 'rate' => $r['rate'] / 2, 'amount' => $half, 'taxable' => $r['taxable']];
                $rows[] = ['label' => 'SGST', 'rate' => $r['rate'] / 2, 'amount' => round($r['tax'] - $half, 2), 'taxable' => $r['taxable']];
            } else {
                $rows[] = ['label' => $label, 'rate' => $r['rate'], 'amount' => $r['tax'], 'taxable' => $r['taxable']];
            }
        }

        return $rows;
    }
}

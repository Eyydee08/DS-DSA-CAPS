<?php
function mergeSortArchives(array $archives)
{
    $count = count($archives);
    if ($count < 2) {
        return $archives;
    }

    $source = array_values($archives);
    $target = $source;

    for ($width = 1; $width < $count; $width *= 2) {
        for ($start = 0; $start < $count; $start += 2 * $width) {
            $middle = min($start + $width, $count);
            $end = min($start + 2 * $width, $count);
            $left = $start;
            $right = $middle;

            for ($output = $start; $output < $end; $output++) {
                if ($left < $middle && (
                    $right >= $end ||
                    (int) $source[$left]['archive_year'] > (int) $source[$right]['archive_year'] ||
                    (
                        (int) $source[$left]['archive_year'] === (int) $source[$right]['archive_year'] &&
                        (int) $source[$left]['archive_id'] >= (int) $source[$right]['archive_id']
                    )
                )) {
                    $target[$output] = $source[$left];
                    $left++;
                } else {
                    $target[$output] = $source[$right];
                    $right++;
                }
            }
        }

        $temporary = $source;
        $source = $target;
        $target = $temporary;
    }

    return $source;
}

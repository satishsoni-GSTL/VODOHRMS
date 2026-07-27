<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Generic reader that turns row 1 into associative array keys
 * (spaces/case normalized to snake_case by Maatwebsite) for any import template.
 */
class RawSheetReader implements WithHeadingRow {}

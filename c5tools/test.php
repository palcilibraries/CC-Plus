<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($argc !== 2) {
    fprintf(STDERR, "usage: %s <report file>\n", $argv[0]);
    exit(5);
}

$report = null;
$debugItems = true;
try {
    $report = \ubfr\c5tools\Report::createFromFile($argv[1]);
    $checkResult = $report->getCheckResult();
} catch (Exception $e) {
    $checkResult = new \ubfr\c5tools\CheckResult();
    try {
        $checkResult->fatalError($e->getMessage(), $e->getMessage());
    } catch (\ubfr\c5tools\ParseException $e) {
        // ignore expected exception
    }
}

print $checkResult->asText();
print "\n";
print memory_get_usage() . ' / ' . memory_get_usage(true) . "\n";
print memory_get_peak_usage() . ' / ' . memory_get_peak_usage(true) . "\n";

$spreadsheet = null;
if ($report !== null) {
    try {
        $spreadsheet = $report->getCheckResultAsSpreadsheet();
    } catch (Exception $e) {
        // ignore exception, method might fail if there are serious issues with the report header
    }
}
if ($spreadsheet === null) {
    $spreadsheet = $checkResult->asSpreadsheet();
}
$writer = new Xlsx($spreadsheet);
$writer->save(pathinfo($argv[1], PATHINFO_FILENAME) . '-Validation-Result.xlsx');

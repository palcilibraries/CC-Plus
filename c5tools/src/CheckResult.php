<?php
namespace ubfr\c5tools;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CheckResult
{

    const CR_PASSED = 0;

    const CR_NOTICE = 1;

    const CR_WARNING = 2;

    const CR_ERROR = 3;

    const CR_CRITICAL = 4;

    const CR_FATAL = 5;

    protected static $levelNames = [
        self::CR_PASSED => 'Passed',
        self::CR_NOTICE => 'Notice',
        self::CR_WARNING => 'Warning',
        self::CR_ERROR => 'Error',
        self::CR_CRITICAL => 'Critical error',
        self::CR_FATAL => 'Fatal error'
    ];

    protected $result = self::CR_PASSED;

    protected $messages = array();

    protected $summaryMessages = array();

    protected $numberOfMessages = [
        self::CR_PASSED => 0,
        self::CR_NOTICE => 0,
        self::CR_WARNING => 0,
        self::CR_ERROR => 0,
        self::CR_CRITICAL => 0,
        self::CR_FATAL => 0
    ];

    protected function addMessage($level, $message, $position, $data, $hint, $summary = null)
    {
        if ($this->result < $level) {
            $this->result = $level;
        }
        $this->messages[] = [
            'l' => $level,
            'm' => $message,
            'p' => $position,
            'd' => $data,
            'h' => $hint,
            's' => $summary
        ];
        $this->numberOfMessages[$level] ++;
    }

    protected function addSummaryMessage($level, $summary, $message, $position, $data, $hint)
    {
        $this->addMessage($level, $message, $position, $data, $hint, $summary);

        if ($hint !== null) {
            $summary = $summary . ', ' . $hint;
        }
        if (! isset($this->summaryMessages[$level])) {
            $this->summaryMessages[$level] = [];
        }
        if (! isset($this->summaryMessages[$level][$summary])) {
            $this->summaryMessages[$level][$summary] = 1;
        } else {
            $this->summaryMessages[$level][$summary] ++;
        }
    }

    public function fatalError($summary, $message)
    {
        $this->addSummaryMessage(self::CR_FATAL, $summary, $message, null, null, null);

        throw new ParseException("Fatal Error: {$message}");
    }

    public function addCriticalError($summary, $message, $position, $data, $hint = null)
    {
        $this->addSummaryMessage(self::CR_CRITICAL, $summary, $message, $position, $data, $hint);
    }

    public function addError($summary, $message, $position, $data, $hint = null)
    {
        $this->addSummaryMessage(self::CR_ERROR, $summary, $message, $position, $data, $hint);
    }

    public function addWarning($summary, $message, $position, $data, $hint = null)
    {
        $this->addSummaryMessage(self::CR_WARNING, $summary, $message, $position, $data, $hint);
    }

    public function addNotice($summary, $message, $position, $data, $hint = null)
    {
        $this->addSummaryMessage(self::CR_NOTICE, $summary, $message, $position, $data, $hint);
    }

    public static function getLevelNames()
    {
        return self::$levelNames;
    }

    public function getLevelName($level)
    {
        if (! isset(self::$levelNames[$level])) {
            throw new \Exception('System Error - CheckResult: invalid level ' . $level);
        }
        return self::$levelNames[$level];
    }

    public function getNumberOfMessages($level)
    {
        if (! isset(self::$levelNames[$level])) {
            throw new \Exception('System Error - CheckResult: invalid level ' . $level);
        }

        return $this->numberOfMessages[$level];
    }

    public function asText($summaryThreshold = 25)
    {
        if (! is_int($summaryThreshold) || $summaryThreshold < 0) {
            throw new \Exception('System Error - Checkresult: invalid summaryThreshold ' . $summaryThreshold);
        }

        usort($this->messages, array(
            '\ubfr\c5tools\CheckResult',
            'sortMessages'
        ));

        $text = '';
        foreach ($this->messages as $message) {
            $text .= $this->getMessageAsText($message, $summaryThreshold);
        }
        if ($summaryThreshold > 0) {
            foreach ($this->summaryMessages as $level => $levelSummaryMessages) {
                foreach ($levelSummaryMessages as $summary => $numMessages) {
                    if ($numMessages >= $summaryThreshold) {
                        $text .= $this->getSummaryMessageAsText($level, $summary, $numMessages);
                    }
                }
            }
        }
        $text .= 'Result at ' . date('Y-m-d H:i:s') . ': ' . $this->getLevelName($this->result) . "\n";

        return $text;
    }

    public function asArray($summaryThreshold = 25)
    {
        if (! is_int($summaryThreshold) || $summaryThreshold < 0) {
            throw new \Exception('System Error - Checkresult: invalid summaryThreshold ' . $summaryThreshold);
        }

        usort($this->messages, array(
            '\ubfr\c5tools\CheckResult',
            'sortMessages'
        ));

        $array = [];
        foreach ($this->messages as $message) {
            if (! $this->useSummaryMessage($message, $summaryThreshold)) {
                $array[] = [
                    'number' => 1,
                    'level' => $message['l'],
                    'header' => $this->getLevelPositionName($message),
                    'data' => ($message['d'] !== null ? $message['d'] : ''),
                    'message' => $message['m'] . ($message['h'] !== null ? ', ' . $message['h'] : '') . '.'
                ];
            }
        }
        if ($summaryThreshold > 0) {
            foreach ($this->summaryMessages as $level => $levelSummaryMessages) {
                foreach ($levelSummaryMessages as $summary => $numMessages) {
                    if ($numMessages >= $summaryThreshold) {
                        $array[] = [
                            'number' => $numMessages,
                            'level' => $level,
                            'header' => $numMessages . ' ' . $this->getLevelName($level) .
                            ($numMessages !== 1 ? 's' : ''),
                            'data' => '(Summary)',
                            'message' => $summary . '.'
                        ];
                    }
                }
            }
        }

        return $array;
    }

    public function asSpreadsheet($summaryThreshold = 500)
    {
        if (! is_int($summaryThreshold) || $summaryThreshold < 0) {
            throw new \Exception('System Error - Checkresult: invalid summaryThreshold ' . $summaryThreshold);
        }

        usort($this->messages, array(
            '\ubfr\c5tools\CheckResult',
            'sortMessages'
        ));

        $spreadsheet = new Spreadsheet();
        $defaultStyle = $spreadsheet->getDefaultStyle();
        $defaultStyle->getFont()->setName('Arial');
        $defaultStyle->getFont()->setSize(11);
        $defaultStyle->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Validation Result');

        $numFatal = $this->getNumberOfMessages(self::CR_FATAL);
        $numCritical = $this->getNumberOfMessages(self::CR_CRITICAL);
        $numErrors = $numFatal + $numCritical + $this->getNumberOfMessages(self::CR_ERROR);
        $numWarnings = $this->getNumberOfMessages(self::CR_WARNING);
        $numNotices = $this->getNumberOfMessages(self::CR_NOTICE);
        $row = 1;

        if ($numFatal !== 0) {
            $result = 'The validation failed with a fatal error at ';
        } elseif ($numCritical !== 0) {
            $result = 'The validation failed with ' . ($numCritical > 1 ? 'critical errors at ' : 'a critical error at ');
        } elseif ($numErrors + $numWarnings > 0) {
            $result = 'The report did not pass the validation at ';
        } elseif ($numNotices !== 0) {
            $result = 'The report passed the (not yet complete) validation at ';
        } else {
            $result = 'The report passed the (not yet complete) validation at ';
        }
        $result .= date('Y-m-d H:i:s') . '.';
        $worksheet->mergeCells("A{$row}:D{$row}");
        $worksheet->setCellValue("A{$row}", $result);
        $row ++;

        if ($numErrors === 0 && $numWarnings === 0 && $numNotices === 0) {
            return $spreadsheet;
        }

        $review = [];
        if ($numErrors > 0) {
            $review[] = "{$numErrors} error" . ($numErrors > 1 ? 's' : '');
        }
        if ($numWarnings > 0) {
            $review[] = "{$numWarnings} warning" . ($numWarnings > 1 ? 's' : '');
        }
        if ($numNotices > 0) {
            $review[] = "{$numNotices} notice" . ($numNotices > 1 ? 's' : '');
        }
        $worksheet->mergeCells("A{$row}:D{$row}");
        $worksheet->setCellValue("A{$row}", 'Please review the ' . implode(' and ', $review) . '.');
        $row += 2;

        $columns = [
            'A' => 'Level',
            'B' => 'Position',
            'C' => 'Data',
            'D' => 'Message'
        ];
        foreach ($columns as $column => $header) {
            $worksheet->setCellValue("{$column}{$row}", $header);
        }
        $worksheet->setAutoFilter("A{$row}:D{$row}");
        $row ++;
        $worksheet->freezePane("A{$row}");

        foreach ($this->messages as $message) {
            if (! $this->useSummaryMessage($message, $summaryThreshold)) {
                $worksheet->setCellValue("A{$row}", $this->getLevelName($message['l']));
                $worksheet->setCellValue("B{$row}", ucfirst($this->getPositionName($message)));
                $worksheet->setCellValue("C{$row}", $message['d'] !== null ? $message['d'] : '');
                $worksheet->setCellValue("D{$row}",
                    $message['m'] . ($message['h'] !== null ? ', ' . $message['h'] : '') . '.');
                $row ++;
            }
        }
        if ($summaryThreshold > 0) {
            foreach ($this->summaryMessages as $level => $levelSummaryMessages) {
                foreach ($levelSummaryMessages as $summary => $numMessages) {
                    if ($numMessages >= $summaryThreshold) {
                        $worksheet->setCellValue("A{$row}", $this->getLevelName($level));
                        $worksheet->setCellValue("B{$row}", '(Summary)');
                        $worksheet->setCellValue("C{$row}", '(Summary)');
                        $worksheet->setCellValue("D{$row}",
                            $numMessages . ' ' . $this->getLevelName($level) . ($numMessages !== 1 ? 's' : '') . ': ' .
                            $summary . '.');
                        $row ++;
                    }
                }
            }
        }

        foreach (array_keys($columns) as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    protected function sortMessages($a, $b)
    {
        // fatal error always at the end
        if ($a['l'] === self::CR_FATAL) {
            return 1;
        }
        if ($b['l'] === self::CR_FATAL) {
            return - 1;
        }

        $ma = [];
        $mb = [];
        if (preg_match('/^([A-Z]*)([0-9]*)$/', $a['p'], $ma) && preg_match('/^([A-Z]*)([0-9]*)$/', $b['p'], $mb)) {
            // tabular report: sort by row, column
            if ($ma[2] !== '' && $mb[2] !== '') {
                if ($ma[2] < $mb[2]) {
                    return - 1;
                }
                if ($ma[2] > $mb[2]) {
                    return 1;
                }
            } elseif ($ma[2] !== '') {
                return - 1;
            } elseif ($mb[2] !== '') {
                return 1;
            }
            if ($ma[1] !== '' && $mb[1] !== '') {
                if (strlen($ma[1]) > strlen($mb[1])) {
                    return 1;
                } elseif (strlen($ma[1]) < strlen($mb[1])) {
                    return - 1;
                }
                if ($ma[1] < $mb[1]) {
                    return - 1;
                } elseif ($ma[1] > $mb[1]) {
                    return 1;
                }
            } elseif ($ma[1] !== '') {
                return - 1;
            } elseif ($mb[1] !== '') {
                return 1;
            }
        } else {
            // json report: sort by position
            if (($cmp = strnatcmp($a['p'], $b['p'])) !== 0) {
                return $cmp;
            }
        }

        // same position: sort by level
        if ($a['l'] > $b['l']) {
            return - 1;
        } elseif ($a['l'] < $b['l']) {
            return 1;
        }

        // same level: sort by data
        if ($a['d'] > $b['d']) {
            return - 1;
        } elseif ($a['d'] < $b['d']) {
            return 1;
        }

        // same data: sort by message
        if ($a['m'] > $b['m']) {
            return - 1;
        } elseif ($a['m'] < $b['m']) {
            return 1;
        }

        return 0;
    }

    protected function useSummaryMessage($message, $summaryThreshold)
    {
        if ($summaryThreshold === 0) {
            return false;
        }
        $summary = $message['s'];
        if ($message['h'] !== null) {
            $summary .= ', ' . $message['h'];
        }
        return ($this->summaryMessages[$message['l']][$summary] >= $summaryThreshold);
    }

    protected function getLevelPositionName($message)
    {
        $positionName = $this->getPositionName($message);
        if ($positionName !== '') {
            $positionName = (substr($positionName, 0, 7) === 'element' ? ' at ' : ' in ') . $positionName;
        }
        return $this->getLevelName($message['l']) . $positionName;
    }

    protected function getPositionName($message)
    {
        $positionName = '';
        if ($message['p'] !== null) {
            if (preg_match('/^[A-Z]+[0-9]+$/', $message['p'])) {
                $positionName = 'cell';
            } elseif (preg_match('/^[0-9]+$/', $message['p'])) {
                $positionName = 'row';
            } elseif (preg_match('/^[A-Z]+$/', $message['p'])) {
                $positionName = 'column';
            } else {
                $positionName = 'element';
            }
            if ($positionName !== null) {
                $positionName .= ' ';
            }
            $positionName .= $message['p'];
        }
        return $positionName;
    }

    protected function getMessageAsText($message, $summaryThreshold)
    {
        if ($this->useSummaryMessage($message, $summaryThreshold)) {
            return '';
        }

        $text = $this->getLevelPositionName($message) . ': ';
        if ($message['d'] !== null) {
            $text .= $message['d'];
        }
        $text .= "\n  " . $message['m'];
        if ($message['h'] !== null) {
            $text .= ', ' . $message['h'];
        }
        $text .= ".\n";

        return $text;
    }

    protected function getSummaryMessageAsText($level, $message, $numMessages)
    {
        return ($numMessages . ' ' . $this->getLevelName($level) . ($numMessages !== 1 ? 's' : '') . ': ' . $message .
            ".\n");
    }
}

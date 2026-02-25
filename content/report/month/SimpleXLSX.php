<?php
/**
 * SimpleXLSX - Simple XLSX Parser
 * Lightweight parser for reading .xlsx files without external dependencies
 */

class SimpleXLSX {
    private $sheets = [];
    private $sharedStrings = [];
    private $styles = [];
    private static $error = '';
    
    public static function parse($filePath) {
        self::$error = '';
        
        if (!file_exists($filePath)) {
            self::$error = "File not found: $filePath";
            return false;
        }
        
        $instance = new self();
        if (!$instance->load($filePath)) {
            return false;
        }
        
        return $instance;
    }
    
    public static function parseError() {
        return self::$error;
    }
    
    private function load($filePath) {
        $zip = new ZipArchive();
        
        if ($zip->open($filePath) !== true) {
            self::$error = "Cannot open file as ZIP archive";
            return false;
        }
        
        // Load shared strings
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $this->parseSharedStrings($sharedStringsXml);
        }
        
        // Load worksheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) {
            self::$error = "Cannot find worksheet";
            $zip->close();
            return false;
        }
        
        $this->parseSheet($sheetXml);
        
        $zip->close();
        return true;
    }
    
    private function parseSharedStrings($xml) {
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        
        $siNodes = $doc->getElementsByTagName('si');
        foreach ($siNodes as $si) {
            $text = '';
            $tNodes = $si->getElementsByTagName('t');
            foreach ($tNodes as $t) {
                $text .= $t->nodeValue;
            }
            $this->sharedStrings[] = $text;
        }
    }
    
    private function parseSheet($xml) {
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        
        $rows = [];
        $rowNodes = $doc->getElementsByTagName('row');
        
        foreach ($rowNodes as $rowNode) {
            $rowIndex = (int)$rowNode->getAttribute('r');
            $row = [];
            $maxCol = 0;
            
            $cellNodes = $rowNode->getElementsByTagName('c');
            foreach ($cellNodes as $cellNode) {
                $cellRef = $cellNode->getAttribute('r');
                $colIndex = $this->columnToIndex($cellRef);
                $maxCol = max($maxCol, $colIndex);
                
                $value = '';
                $type = $cellNode->getAttribute('t');
                
                $vNode = $cellNode->getElementsByTagName('v')->item(0);
                if ($vNode) {
                    $value = $vNode->nodeValue;
                    
                    // Shared string
                    if ($type === 's') {
                        $value = $this->sharedStrings[(int)$value] ?? '';
                    }
                }
                
                // Inline string
                $isNode = $cellNode->getElementsByTagName('is')->item(0);
                if ($isNode) {
                    $tNodes = $isNode->getElementsByTagName('t');
                    $value = '';
                    foreach ($tNodes as $t) {
                        $value .= $t->nodeValue;
                    }
                }
                
                $row[$colIndex] = $value;
            }
            
            // Fill gaps with empty strings
            $filledRow = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $filledRow[$i] = $row[$i] ?? '';
            }
            
            $rows[$rowIndex] = $filledRow;
        }
        
        // Sort by row index and convert to 0-based array
        ksort($rows);
        $this->sheets[0] = array_values($rows);
    }
    
    private function columnToIndex($cellRef) {
        preg_match('/^([A-Z]+)/', $cellRef, $matches);
        $col = $matches[1];
        $index = 0;
        $length = strlen($col);
        
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        
        return $index - 1;
    }
    
    public function rows($sheetIndex = 0) {
        return $this->sheets[$sheetIndex] ?? [];
    }
    
    public function rowsEx($sheetIndex = 0) {
        return $this->rows($sheetIndex);
    }
    
    public function sheetNames() {
        return ['Sheet1'];
    }
    
    public function sheetsCount() {
        return count($this->sheets);
    }
}
?>

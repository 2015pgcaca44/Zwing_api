<?php

namespace App\Http\CustomClasses;


class PrintJsonInvoice
{
    private $result;
    private $endLines = null;
    private $defaultSize = 24;
    private $currentAlign = null;
    private $deviceConfig;

    //          Font    Words
    //            20 - 38
    //            22 - 34
    //            24 - 32
    //            27 - -
    //            30 - 25
    //            36 - 21

    // SUNMI   - Orange device.
    // Basewin - Red Device
    // PAX     -  Pinelab
    function __construct($deviceType, $params = [])
    {
        switch ($deviceType) {
            case 'SUNMI':
                $this->deviceConfig = new SunmiPrintConfiguration($params);
                break;
            case 'basewin':
                $this->deviceConfig = new BasewinPrintConfiguration($params);
                break;
            case 'POS-58':
                $this->deviceConfig = new ThermalPrintConfiguration($params);
                break;
            case 'TP 3260';
                   $this->deviceConfig = new RaiserPrintConfiguration($params);
                  break;    
            case 'PAX':
                $this->deviceConfig = new PinelabPrintConfiguration($params);
                break;
            case 'unknown_Sunyard':
                $this->deviceConfig = new MswipePrintConfiguration($params);
                break;
            case 'LENOVO':
                $this->deviceConfig = new MswipePrintConfiguration($params);
                break;
            case 'Posiflex PP8800 Printer':
                $this->deviceConfig = new PosiflexPrintConfiguration($params);
                break;
            case 'bbpos';
                   $this->deviceConfig = new BbposPrintConfiguration($params);
                  break;     
            default:
                $this->deviceConfig = new BasewinPrintConfiguration($params);
        }
        $this->currentAlign = null;
        $this->endLines = $this->deviceConfig->endLines();
        $this->defaultSize = $this->deviceConfig->getDefaultSize();
        $this->result = [];
    }

    function getEndLines($text, $size, $isBold, $isItalic, $key)
    {       
        $text = $this->endLines;
        return $this->addLine($text, $size, $isBold, $isItalic, $key);;
    }

    // Deprecated function
    function createStructure($text, $isBold, $isItalic, $align, $size, $isOpen, $isClose)
    {
        $size = $this->deviceConfig->getFontSize($size);
        $outputString = $isOpen ? "<$align>" : "";
        $outputString .= "<size=$size>";
        $outputString .= $isBold ? "<bold>" : ($isItalic ? "<italic>" : "<normal>");
        $outputString .= "<text>$text</text>";
        $outputString .= $isBold ? "</bold>" : ($isItalic ? "</italic>" : "</normal>");
        $outputString .= "</size>";
        $outputString .= ($isClose && !empty($align)) ? "</$align>" : "";

        $this->result .= $outputString;
        return true;
    }

    function startCenter()
    {
        if ($this->currentAlign == 'center') {
            return;
        }
        $this->checkCloseAlign();
        $this->result .= "<center>";
        $this->currentAlign = 'center';
        return true;
    }

    function startLeft()
    {
        if ($this->currentAlign == 'left') {
            return;
        }
        $this->checkCloseAlign();
        $this->result .= "<left>";
        $this->currentAlign = 'left';
        return true;
    }

    function startRight()
    {
        if ($this->currentAlign == 'right') {
            return;
        }
        $this->checkCloseAlign();
        $this->result .= "<right>";
        $this->currentAlign = 'right';
        return true;
    }

    function endCenter()
    {
        if (!$this->currentAlign || $this->currentAlign != 'center') return;
        $this->result .= "</center>";
        $this->currentAlign = null;
        return true;
    }

    function endRight()
    {
        if (!$this->currentAlign || $this->currentAlign != 'right') return;
        $this->result .= "</right>";
        $this->currentAlign = null;
        return true;
    }

    function endLeft()
    {
        if (!$this->currentAlign || $this->currentAlign != 'left') return;
        $this->result .= "</left>";
        $this->currentAlign = null;
        return true;
    }

    private function formatAlignLeft($orgItemName, $totalLength)
    {
        $orgLength = strlen($orgItemName);
        if ($orgLength == $totalLength) {
            return $orgItemName;
        } else if ($orgLength < $totalLength) {
            return $this->addSpacesToEnd($orgItemName, $totalLength - $orgLength);
        } else {
            return substr($orgItemName, 0, $totalLength);
        }
    }

    private function addSpacesToEnd($string, $number)
    {
        for ($i = 0; $i < $number; ++$i) {
            $string .= " ";
        }
        return $string;
    }

    private function formatAlignRight($orgItemName, $totalLength)
    {
        $orgLength = strlen($orgItemName);
        if ($orgLength == $totalLength) {
            return $orgItemName;
        } else if ($orgLength < $totalLength) {
            return $this->addSpacesToFront($orgItemName, $totalLength - $orgLength);
        } else {
            return substr($orgItemName, 0, $totalLength);
        }
    }

    private function addSpacesToFront($string, $number)
    {
        for ($i = 0; $i < $number; ++$i) {
            $string = " " . $string;
        }
        return $string;
    }

    private function adjustLine($tempValue, $size)
    {
        if (strlen($tempValue) > $this->deviceConfig->getLength($size)) {
            $garbageValue = substr($tempValue, 0, $this->deviceConfig->getLength($size) - 1);
            $index = strripos($garbageValue, " ");
            $firstLine = substr($garbageValue, 0, $index);
            $secondLine = substr($tempValue, $index + 1, strlen($tempValue));
            $secondLine = $this->adjustLine($secondLine, $size);

            return $firstLine . "\n" . $secondLine;
        } else {
            return $tempValue;
        }
    }

    private function charOfLength($character, $length)
    {
        $result = "";
        for ($i = 0; $i < $length; ++$i) {
            $result .= $character;
        }
        //        if($newLine) $result .= '\n';
        return $result;
    }

    private function checkCloseAlign()
    {
        if ($this->currentAlign == null) {
            return;
        } else {
            switch ($this->currentAlign) {
                case 'left':
                    $this->endLeft();
                    break;
                case 'right':
                    $this->endRight();
                    break;
                case 'center':
                    $this->endCenter();
                    break;
            }
        }
    }

    // If any align tag is opened then continues else starts left align
    function addLine($text, $size, $isBold = false, $isItalic = false, $key = '')
    {
        
        // if (!$this->currentAlign) $this->startLeft();
        $size = $this->deviceConfig->getFontSize($size);
        $charSize = $this->deviceConfig->getLength($size);
        $outputJson = [];
        $outputJson['bold'] = $isBold ? 1 : 0;
        $outputJson['italic'] = $isItalic ? 1 : 0;
        $outputJson['size'] = $size;
        if(is_array($text)){
            $outputJson['text'] = $text;
        }else{
            $outputJson['text'] = wordwrap($text, $charSize); 
        }
        $outputJson['key'] = $key;
        // dd($charSize);
        // $outputString = "<size=$size>";
        // $outputString .= $isBold ? "<bold>" : ($isItalic ? "<italic>" : "<normal>");
        // $outputString .= "<text>$text" . "\n</text>";
        // $outputString .= $isBold ? "</bold>" : ($isItalic ? "</italic>" : "</normal>");
        // $outputString .= "</size>";

        $this->result[] = $outputJson;
        return true;
    }

    function addTcLine($text, $size, $isBold = false, $isItalic = false, $key = ''){
   
     // if (!$this->currentAlign) $this->startLeft();
        $size = $this->deviceConfig->getFontSize($size);
        $wordlength = $this->deviceConfig->getLength($size);
        $text = wordwrap($text, $wordlength, "\n", false);
        $outputJson = [];
        $outputJson['bold'] = $isBold ? 1 : 0;
        $outputJson['italic'] = $isItalic ? 1 : 0;
        $outputJson['size'] = $size;
        $outputJson['text'] = wordwrap($text, $wordlength);
        $outputJson['key'] = $key;
        // $outputString = "<size=$size>";
        // $outputString .= $isBold ? "<bold>" : ($isItalic ? "<italic>" : "<normal>");
        // $outputString .= "<text>$text" . "\n</text>";
        // $outputString .= $isBold ? "</bold>" : ($isItalic ? "</italic>" : "</normal>");
        // $outputString .= "</size>";

        $this->result[] = $outputJson;
        return true;


    }

    public function addLineWithWrap($text, $size, $isBold = false, $isItalic = false)
    {
        // if ($this->currentAlign != 'left') $this->startLeft();
        $this->addLine($this->adjustLine($text, $size), $size, $isBold, $isItalic);
    }

    // Closes previous align and start left
    public function addLineLeft($text, $size, $isBold = false, $isItalic = false, $key = '')
    {
        $pageLength = $this->deviceConfig->getLength($size);
        // $this->startLeft();
        $item = [];
        $item['name'] = $text;
        $item['value'] = '';
        $item['char_length'] = $pageLength;
        // $item['size'] = $size;
        // if($key == 'invoice_no'){
        //     $item['name'] = 'Invoice No: ';
        //     $item['value'] = '';
        // }else if($key == 'invoice_date_time'){
        //     $item['name'] = 'Date: ';
        //     $item['value'] = '';
        // }else if($key == 'cashier_name'){
        //     $item['name'] = 'Cashier: ';
        //     $item['value'] = '';
        // }else if($key == 'customer_mob'){
        //     $item['name'] = 'Customer Mobile: ';
        //     $item['value'] = '';
        // }else if($key == 'amount_words'){
        //     $item['name'] = '';
        //     $item['value'] = '';
        // }else if($key == 'customer_paid'){
        //     $item['name'] = 'Customer Paid: ';
        //     $item['value'] = '';
        // }else if($key == 'balance_refund'){
        //     $item['name'] = 'Balance Refund: ';
        //     $item['value'] = '';
        // }
        // if($key == 'amount_words' || $key == 'customer_paid' || $key == 'balance_refund'){
        //     $text = '';
        // }
        $text = $item;
        return $this->addLine($text, $size, $isBold, $isItalic, $key);
    }

    public function addTcLineLeft($text, $size, $isBold = false, $isItalic = false, $key = ''){
        // $this->startLeft();
        return $this->addTcLine($text, $size, $isBold, $isItalic, $key);

    }

    // Closes previous align and start right
    public function addLineRight($text, $size, $isBold = false, $isItalic = false)
    {
        // $this->startRight();
        return $this->addLine($text, $size, $isBold, $isItalic);
    }

    // Closes previous align and start center
    public function addLineCenter($text, $size, $isBold = false, $isItalic = false, $key = '')
    {
        // $this->startCenter();
        return $this->addLine($text, $size, $isBold, $isItalic, $key);
    }

    public function addLineCenterWrap($text, $size, $isBold = false, $isItalic = false)
    {
        // $this->startCenter();
        return $this->addLine($this->adjustLine($text, $size), $size, $isBold, $isItalic);
    }

    // Works in any align
    public function leftRightStructure($textLeft, $textRight, $size, $isBold = false, $isItalic = false, $key = '')
    {
        $pageLength = $this->deviceConfig->getLength($size);
        // $leftLength = strlen($textLeft);
        // $rightLength = strlen($textRight);
        // if ($leftLength + $rightLength >= $pageLength) {
        //     if ($leftLength >= $rightLength) {
        //         $textLeft = substr($textLeft, 0, $pageLength - $rightLength - 1);
        //     } else {
        //         $textRight = substr($textRight, 0, $pageLength - $rightLength - 1);
        //     }
        // } else {
        //     $textRight = $this->formatAlignRight($textRight, $pageLength - $leftLength);
        // }
        $result = [];
        
        $item['textLeft'] = $textLeft;
        $item['textRight'] = $textRight;
        $item['char_length'] = $pageLength;
        // $item['size'] = $size;

        $result = $item;

        $this->addLine($result, $size, $isBold, $isItalic, $key);
    }

    // Closes previous align and start center
    public function tableStructure($colArray, $weightArray, $size, $isBold = false, $isItalic = false, $key = '', $subkey = '')
    {
        if (count($colArray) != count($weightArray) || gettype($colArray) != 'array' || gettype($weightArray) != 'array') {
            return false;
        } else {
            $weightSum = 0;
            $spaceUsed = 0;
            $result = [];
            $result1 = '';
            foreach ($weightArray as $weight) {
                $weightSum += $weight;
            }

            foreach ($colArray as $index => $column) {
                // if ($index < count($colArray) - 1) {
                    $colSpace = (int) (($this->deviceConfig->getLength($size) / $weightSum) * $weightArray[$index]);
                    // $spaceUsed += $colSpace;
                    // $result1 .= $this->formatAlignLeft($column, $colSpace);
                    // $item->name = $column[$index];
                    // $item->key = $subkey[$index];
                    if($key == 'order_summary'){
                        $item['name'] = '';
                        $item['key'] = $subkey[$index];
                        $item['length'] = $colSpace;
                        // $item['size'] = $size;
                    }else{
                        $item['name'] = $colArray[$index];
                        $item['key'] = $subkey[$index];
                        $item['length'] = $colSpace;
                        // $item['size'] = $size;
                    }
                    // $item->key = 'key';
                    // $itemJson = (object)$item;
                    // dd($item);
                    $result[$index] = $item;
                    // dd($result[$index]);
                    // dd($itemJson);
                   // array_push($result, $itemJson);
                    
                // } 

                // else {
                //     // $colSpace = $this->deviceConfig->getLength($size) - $spaceUsed;
                //     // $result .= $this->formatAlignRight($column, $colSpace);
                //     if (strlen($result) == 35) {
                //         //                        echo '_' . $result . '_'.$this->formatAlignRight($column, $colSpace).'_'.$colSpace.'_';
                //     }
                // }
            }
            // dd($result);
            // if ($this->currentAlign != 'center') $this->startCenter();
            return $this->addLine($result, $size, $isBold, $isItalic, $key);
        }
    }

    // Closes previous align and start center
    public function addDivider($divideChar, $size, $isBold = false, $isItalic = false, $key = '')
    {
        // if ($this->currentAlign != 'center') $this->startCenter();
        $text = $this->charOfLength($divideChar, $this->deviceConfig->getLength($size));
        return $this->addLine($text, $size, $isBold, $isItalic, $key);
    }

    // Closes previous align adds end tag
    public function getFinalResult()
    {
        // $this->checkCloseAlign();
        return $this->result;
    }

    // Closes previous align and start left
    public function numToWords($number, $size, $isBold = false, $isItalic = false)
    {
        // if ($this->currentAlign != 'left') $this->startLeft();
        $this->addLine($this->adjustLine(ucfirst(numberTowords(round($number))), $size), $size, $isBold, $isItalic);
    }

    public function termcondition(){


    } 
}

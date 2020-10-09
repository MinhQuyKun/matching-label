<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Storage;
use setasign\Fpdi\Tfpdf;

class MatchingController extends Controller
{
    /*
    * Matching default label
    */
    public function matching (Request $request, Parser $parser)
    {

        if (isset($request['trackingCodeColumn']) and isset($request['shippingFilePath']) and isset($request['labelsFilePath'])) {

            $trackingColumn = $request['trackingCodeColumn'];
            $fileTracking = $request['shippingFilePath'];
            $file = $request['labelsFilePath'];

            $pdf = $parser->parseFile($file);
            $pages = $pdf->getPages();

            // Fetch excel file
            $spreadsheet = IOFactory::load($fileTracking);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            // Create pdf template
            $n = 1;
            $lastTrackingCode = null;
            $trackingCodePrinted = '';

            for ($i=0; $i < count($pages); $i++) {
            // foreach ($pages as $key => $page) {

                if ($trackingCodePrinted == $lastTrackingCode) {
                    $newPdf = new Tfpdf\Fpdi();
                    $newPdf->setSourceFile($file);
                }

                // Get text pdf file
                $text = $pages[$i]->getText();
                // Set current tracking code
                $currentTrackingCode = null;

                // For excel get tracking code
                foreach ($sheetData as $rows => $k) {

                    $continue = false;

                    foreach ($k as $key => $value) {
                        if ($key == $trackingColumn and $value !== 'tracking_code') {

                            // Detect tracking code
                            if (strpos($text, $value) !== false) {

                                $currentTrackingCode = $value;
                                $continue = true;
                                continue;

                            }
                        }
                    }

                    if ($continue) {
                        continue;
                    }

                }

                if (isset($lastTrackingCode)) {
                    // Import page pdf
                    if ($n > 1) {
                        $templateID = $newPdf->importPage($n-1);
                        $size = $newPdf->getTemplateSize($templateID);
                        $newPdf->addPage($size['orientation'], [$size[0], $size[1]]);
                        $newPdf->useTemplate($templateID);
                    }
                    // If last page -> import page and config template file pdf
                    if ($n == (count($pages))) {
                        $templateID = $newPdf->importPage($n);
                        $size = $newPdf->getTemplateSize($templateID);
                        $newPdf->addPage($size['orientation'], [$size[0], $size[1]]);
                        $newPdf->useTemplate($templateID);
                    }
                    // Save file pdf
                    if ($lastTrackingCode !== $currentTrackingCode or $n == (count($pages))) {

                        try {
                            $newFilename = storage_path('app').'/labels/'.$lastTrackingCode.".pdf";
                            // Save
                            $newPdf->Output($newFilename, "F");
                            $trackingCodePrinted = $currentTrackingCode;
                        } catch (Exception $e) {
                            echo 'Caught exception: ', $e->getMessage(), "\n";
                        }

                    }

                }
                // Check if current tracking code
                if (isset($currentTrackingCode)) {
                    $lastTrackingCode = $currentTrackingCode;
                } else {
                    $lastTrackingCode = null;
                }

                $n = $n + 1;

            }

            // Disconnect excel file
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

        }

    }

    /*
    * Matching DHL label
    */
    public function matchingDhl (Request $request)
    {

    }

}

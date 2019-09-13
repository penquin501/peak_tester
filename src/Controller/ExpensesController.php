<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExpensesController extends AbstractController
{
    /**
     * @Route("/index", name="index")
     */
    public function index(Request $request)
    {
//        $spreadsheet = new Spreadsheet();
//
//        /* @var $sheet \PhpOffice\PhpSpreadsheet\Writer\Xlsx\Worksheet */
//        $sheet = $spreadsheet->getActiveSheet();
//        $sheet->setCellValue('A1', 'Hello World !');
//        $sheet->setTitle("My First Worksheet");
//
//        // Create your Office 2007 Excel (XLSX Format)
//        $writer = new Xlsx($spreadsheet);
//
//        // In this case, we want to write the file in the public directory
//        $publicDirectory = $this->get('kernel')->getProjectDir() . '/public';
//        // e.g /var/www/project/public/my_first_excel_symfony4.xlsx
//        $excelFilepath =  $publicDirectory . '/my_first_excel_symfony4.xlsx';
//
//        // Create the file
//        $writer->save($excelFilepath);
//
//        // Return a text response to the browser saying that the excel was succesfully created
//        return new Response("Excel generated succesfully");
        return $this->render('index.html.twig');
    }
    /**
     * @Route("/check/expenses", name="expenses")
     */
    public function checkExpenses(Request $request)
    {
        $entityManager = $this->getDoctrine()->getManager('default');
        $customEntityManager = $this->getDoctrine()->getManager('custom');

        $sqlPeakRequest = "SELECT bill_no, json_send, json_result " .
            "FROM peak_prepare_to_send " .
            "WHERE peak_method='expenses' " .
            "ORDER BY recorddate DESC";
        $billNoToPrepare = $entityManager->getConnection()->query($sqlPeakRequest);
        $dataPeak = json_decode($this->json($billNoToPrepare)->getContent(), true);

        foreach ($dataPeak as $item) {
            $dataJsonSend = json_decode($item['json_send'], true);
            $dataJsonPeak = json_decode($item['json_result'], true);

            foreach ($dataJsonSend['PeakExpenses']['expenses'] as $itemExpenses) {
                $code = $itemExpenses['code'];//code

                $issuedDate = $itemExpenses['issuedDate'];
                $changeToDate = $this->convertIssuedToDate($issuedDate);

                $strBillNo = $itemExpenses['tags'][1];
                $exBillNo = explode("|", $strBillNo);//$exBillNo[1]

                $strShopName = $itemExpenses['tags'][3];
                $exShopName = explode("|", $strShopName);//$exShopName[1]

//                $strDeliveryFee = $itemExpenses['tags'][4];
//                $exDeliveryFee = explode("|", $strDeliveryFee);//$exDeliveryFee[1]

//                $accountCode = $itemExpenses['paidPayments']['payments'][1]['accountCode'];
                $amount = $itemExpenses['paidPayments']['payments'][1]['amount'];

            }
            if ($dataJsonPeak == '' || !is_array($dataJsonPeak)) {
                $result = "No JSON Result";
                $insertQuery = "INSERT INTO expenses(code, issued_date, bill_no, shop_name, amount_send,result,record_date) " .
                    "VALUES ('" . $code . "','" . $changeToDate . "','" . $exBillNo[1] . "','" . $exShopName[1] . "'," . $amount . ",'" . $result . "',CURRENT_TIMESTAMP())";
            } else {
                $result = "Error Peak Msg";
                $peakResDescResult = $dataJsonPeak['PeakExpenses']['resDesc'];
                if ($dataJsonPeak['PeakExpenses']['resCode'] != 200) {
                    $insertQuery = "INSERT INTO expenses(code, issued_date, bill_no, shop_name, amount_send,peak_res_code,peak_res_desc,result,record_date) " .
                        "VALUES ('" . $code . "','" . $changeToDate . "','" . $exBillNo[1] . "','" . $exShopName[1] . "'," . $amount . ",'" . $dataJsonPeak['PeakExpenses']['resCode'] . "','" . $peakResDescResult . "','" . $result . "',CURRENT_TIMESTAMP()";

                } else {

                    foreach ($dataJsonPeak['PeakExpenses']['expenses'] as $itemReponse) {
//                        $peakResult = $itemReponse['resDesc'];

                        if ($itemReponse['resCode'] != 200) {
//                            $result="Error Peak Msg";
                            $insertQuery = "INSERT INTO expenses(code, issued_date, bill_no, shop_name, amount_send,peak_res_code,peak_res_desc,peak_code,peak_desc,result,record_date) " .
                                "VALUES ('" . $code . "','" . $changeToDate . "','" . $exBillNo[1] . "','" . $exShopName[1] . "'," . $amount . ",'" . $dataJsonPeak['PeakExpenses']['resCode'] . "','" . $peakResDescResult . "','" . $itemReponse['resCode'] . "','" . $itemReponse['resDesc'] . "','" . $result . "',CURRENT_TIMESTAMP())";

                        } else {

                            $status = $itemReponse['status'];
//                            $preTaxAmount = $itemReponse['preTaxAmount'];
//                            $vatAmount = $itemReponse['vatAmount'];
//                            $netAmount = $itemReponse['netAmount'];
                            $paymentAmount = $itemReponse['paymentAmount'];
                            $onlineViewLink = $itemReponse['onlineViewLink'];

                            if ($amount != $paymentAmount) {
                                $result = "Error Amount Not Match";
                            } else {
                                $result = "Correct";
                            }
                            $insertQuery = "INSERT INTO expenses(code, issued_date, bill_no, shop_name, amount_send,amount_peak,peak_res_code,peak_res_desc,peak_code,peak_desc,peak_status,peak_online_link,result,record_date) " .
                                "VALUES ('" . $code . "','" . $changeToDate . "','" . $exBillNo[1] . "','" . $exShopName[1] . "'," . $amount . ",".$paymentAmount.",'" . $dataJsonPeak['PeakExpenses']['resCode'] . "','" . $peakResDescResult . "','" . $itemReponse['resCode'] . "','" . $itemReponse['resDesc'] . "','" . $status . "','" . $onlineViewLink . "','" . $result . "',CURRENT_TIMESTAMP())";

                        }

                    }
                }
            }
            $customEntityManager->getConnection()->query($insertQuery);
        }

        $sqlBillNoError = "SELECT bill_no,peak_res_code,peak_res_desc,peak_code,peak_desc,peak_status,peak_online_link " .
            "FROM expenses " .
            "WHERE result!='Correct'" .
            "ORDER BY record_date ASC";
        $billNoError = $customEntityManager->getConnection()->query($sqlBillNoError);
        $dataBillNoError = json_decode($this->json($billNoError)->getContent(), true);
        if($dataBillNoError==null) {
            $output = ['status' => 'SUCCESS'];
        } else {
            $output = ['status' => 'ERROR',
                'data' => $dataBillNoError
            ];
        }
        return $this->json($output);
    }

    public function convertIssuedToDate($issueDate)
    {
        $splIssueDate = str_split($issueDate);
        $years = '';
        $months = '';
        $days = '';

        for ($y = 0; $y < 4; $y++) {
            $years .= $splIssueDate[$y];
        }
        for ($m = 4; $m < 6; $m++) {
            $months .= $splIssueDate[$m];
        }
        for ($d = 6; $d < 8; $d++) {
            $days .= $splIssueDate[$d];
        }
        return date("Y-m-d", strtotime($years . '-' . $months . '-' . $days));
    }

}

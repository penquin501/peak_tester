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
     * @Route("/save/expenses/info", name="save_expenses")
     */
    public function saveExpensesInfo(Request $request)
    {
        $datePrepare = $request->query->get('datePrepare');
        $testDate = $this->convertStrToDate($datePrepare);
        $nextDate = date('Y-m-d', strtotime("+1 day", strtotime($testDate)));

        $entityManager = $this->getDoctrine()->getManager('default');
        $customEntityManager = $this->getDoctrine()->getManager('custom');

        $sqlParcelTest = "SELECT bill_no FROM expenses";
        $billNoExisted = $customEntityManager->getConnection()->query($sqlParcelTest);
        $dataBillNoExisted = json_decode($this->json($billNoExisted)->getContent(), true);

        $strBillExisted = '';
        foreach ($dataBillNoExisted as $billNo) {
            $strBillExisted .= "'" . $billNo['bill_no'] . "',";
        }
        $billNoTested = rtrim($strBillExisted, ", ");//cut comma at last string

        $sqlPeakRequest = "SELECT bill_no,peak_status,json_send,json_result " .
            "FROM peak_prepare_to_send " .
            "WHERE peak_method='expenses' " .
            "AND (item_date>=DATE('" . $testDate . "') and item_date<DATE('" . $nextDate . "')) " .
//            "AND bill_no NOT IN (" . $billNoTested . ")" .
            "ORDER BY recorddate DESC";
        $billNoToPrepare = $entityManager->getConnection()->query($sqlPeakRequest);
        $dataPeak = json_decode($this->json($billNoToPrepare)->getContent(), true);

        if ($dataPeak == null || $dataPeak == []) {
            $output = ['status' => 'Error No Data'];
        } else {
            foreach ($dataPeak as $item) {
                if ($item['peak_status'] == 'error') {
                    $result = 'Error Peak Not Response';
                    $insertQuery = "INSERT INTO expenses(bill_no, result,record_date) " .
                        "VALUES ('" . $item['bill_no'] . "','" . $result . "',CURRENT_TIMESTAMP())";
                    $customEntityManager->getConnection()->query($insertQuery);
                } else {
                    $dataJsonSend = json_decode($item['json_send'], true);
                    $dataJsonPeak = json_decode($item['json_result'], true);

                    foreach ($dataJsonSend['PeakExpenses']['expenses'] as $itemExpenses) {
                        $code = $itemExpenses['code'];//code

                        $issuedDate = $itemExpenses['issuedDate'];
                        $dueDate = $itemExpenses['dueDate'];
                        $issueDateToDate = $this->convertStrToDate($issuedDate);
                        $dueDateToDate = $this->convertStrToDate($dueDate);

                        $strBillNo = $itemExpenses['tags'][1];
                        $exBillNo = explode("|", $strBillNo);//$exBillNo[1]

                        $merId = explode("-", $exBillNo[1]);//$merId[0]

                        $strShopName = $itemExpenses['tags'][3];
                        $exShopName = explode("|", $strShopName);//$exShopName[1]

                        $amount = $itemExpenses['paidPayments']['payments'][1]['amount'];

                        foreach ($itemExpenses['products'] as $product) {
                            $insertProductItem = "INSERT INTO expenses_send_item(bill_no, item_date, account_sub_id, quantity, price) VALUES " .
                                "('" . $exBillNo[1] . "','" . $issueDateToDate . "','" . $product['accountSubId'] . "'," . $product['quantity'] . "," . $product['price'] . ")";
                            $customEntityManager->getConnection()->query($insertProductItem);
                        }

                    }

                    if ($dataJsonPeak == '' || !is_array($dataJsonPeak)) {
                        $insertQuery = "INSERT INTO expenses(code, issued_date, bill_no, mer_id, shop_name, amount_send,peak_due_date,record_date) " .
                            "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . ",'" . $dueDateToDate . "',CURRENT_TIMESTAMP())";
                    } elseif ($dataJsonPeak['PeakExpenses']['resCode'] != 200) {
//                        $peakResDescResult = $dataJsonPeak['PeakExpenses']['resDesc'];
                        $insertQuery = "INSERT INTO expenses(code, issued_date, bill_no,mer_id, shop_name, amount_send,peak_due_date,peak_res_code,peak_res_desc,record_date) " .
                            "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . ",'" . $dueDateToDate . "','" . $dataJsonPeak['PeakExpenses']['resCode'] . "','" . $dataJsonPeak['PeakExpenses']['resDesc'] . "',CURRENT_TIMESTAMP()";
                    } else {
                        foreach ($dataJsonPeak['PeakExpenses']['expenses'] as $itemReponse) {
                            if ($itemReponse['resCode'] != 200) {
                                $insertQuery = "INSERT INTO expenses(code, issued_date, bill_no,mer_id, shop_name, amount_send,peak_due_date,peak_res_code,peak_res_desc,peak_code,peak_desc,record_date) " .
                                    "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . ",'" . $dueDateToDate . "','" . $dataJsonPeak['PeakExpenses']['resCode'] . "','" . $dataJsonPeak['PeakExpenses']['resDesc'] . "','" . $itemReponse['resCode'] . "','" . $itemReponse['resDesc'] . "',CURRENT_TIMESTAMP())";
                            } else {

                                $status = $itemReponse['status'];
                                $paymentAmount = $itemReponse['paymentAmount'];
                                $onlineViewLink = $itemReponse['onlineViewLink'];

                                foreach ($itemReponse['products'] as $productPeak) {
//                                    dd($productPeak);
                                    $insertProductPeakItem = "INSERT INTO expenses_peak_item(bill_no, item_date, peak_id, account_sub_id, quantity, price, discount) VALUES " .
                                        "('" . $exBillNo[1] . "'," . $issuedDate . ",'" . $productPeak['id'] . "','" . $productPeak['accountSubId'] . "'," . $productPeak['quantity'] . "," . $productPeak['price'] . "," . $productPeak['discount'] . ")";

                                    $customEntityManager->getConnection()->query($insertProductPeakItem);
                                }
                                $insertQuery = "INSERT INTO expenses(code, issued_date, bill_no,mer_id, shop_name, amount_send,amount_peak,peak_due_date,peak_res_code,peak_res_desc,peak_code,peak_desc,peak_status,peak_online_link,record_date) " .
                                    "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . "," . $paymentAmount . ",'" . $dueDateToDate . "','" . $dataJsonPeak['PeakExpenses']['resCode'] . "','" . $dataJsonPeak['PeakExpenses']['resDesc'] . "','" . $itemReponse['resCode'] . "','" . $itemReponse['resDesc'] . "','" . $status . "','" . $onlineViewLink . "',CURRENT_TIMESTAMP())";

                                $output = ['status' => 'SUCCESS'];
                            }

                        }
                    }
                    $customEntityManager->getConnection()->query($insertQuery);
                }

            }
        }
        return $this->json($output);
    }

    public function convertStrToDate($issueDate)
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

    /**
     * @Route("/check/expenses/info",name="check_expenses")
     */
    public function checkExpensesInfo(Request $request)
    {
//        $entityManager = $this->getDoctrine()->getManager('default');
        $customEntityManager = $this->getDoctrine()->getManager('custom');
        $sqlPeakRequest = "SELECT bill_no,amount_send,amount_peak FROM expenses where result_amount is null AND result_quantity is null";
        $billNotTest = $customEntityManager->getConnection()->query($sqlPeakRequest);
        $dataExisted = json_decode($this->json($billNotTest)->getContent(), true);

        if($dataExisted == [])
        {
            $output = ['status' => 'Error No data to check'];
        } else {
            foreach ($dataExisted as $billItem) {
                $sendProductId = [];
                $peakProductId = [];
                $countSendProductQty = 0;
                $countPeakProductQty = 0;
                $sqlSendItem = "SELECT account_sub_id,quantity FROM expenses_send_item " .
                    "WHERE bill_no='" . $billItem['bill_no'] . "'";
                $sendProductItem = $customEntityManager->getConnection()->query($sqlSendItem);
                $products = json_decode($this->json($sendProductItem)->getContent(), true);

                foreach ($products as $productItem) {
                    if (array_key_exists($productItem['account_sub_id'], $sendProductId)) {
                        $sendProductId[$productItem['account_sub_id']] += $productItem['quantity'];
                    } else {
                        $sendProductId[$productItem['account_sub_id']] = 0;
                        $sendProductId[$productItem['account_sub_id']] += $productItem['quantity'];
                    }
                    $countSendProductQty += $productItem['quantity'];
                }
                $sqlPeakItem = "SELECT account_sub_id,quantity FROM expenses_peak_item " .
                    "WHERE bill_no='" . $billItem['bill_no'] . "'";
                $peakProductItem = $customEntityManager->getConnection()->query($sqlPeakItem);
                $peakProducts = json_decode($this->json($peakProductItem)->getContent(), true);

                foreach ($peakProducts as $peakItem) {
                    if (array_key_exists($peakItem['account_sub_id'], $peakProductId)) {
                        $peakProductId[$peakItem['account_sub_id']] += $peakItem['quantity'];
                    } else {
                        $peakProductId[$peakItem['account_sub_id']] = 0;
                        $peakProductId[$peakItem['account_sub_id']] += $peakItem['quantity'];

                    }
                    $countPeakProductQty += $productItem['quantity'];
                }

                ///////////////////////////////////////////TESTING PROCESS//////////////////////////////////////////////

                if (($billItem['amount_send'] == $billItem['amount_peak']) && ($countSendProductQty == $countPeakProductQty)) {
                    $resultQuantity = "Correct";
                    $resultAmount = "Correct";
                } else {
                    $resultQuantity = "Incorrect";
                    $resultAmount = "Incorrect";
                }
                ////////////////////////////////////////////////////////////////////////////////////////////////////////
                $updateResult = "UPDATE expenses SET product_quantity=" . $countSendProductQty . ",peak_product_quantity=" . $countPeakProductQty . ",result_quantity='" . $resultQuantity . "',result_amount='" . $resultAmount . "' " .
                    "WHERE bill_no='" . $billItem['bill_no'] . "'";
                $customEntityManager->getConnection()->query($updateResult);
                $output = ['status' => 'success'];
            }
        }
        return $this->json($output);
    }

}

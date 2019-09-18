<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ReceiptsController extends AbstractController
{
    /**
     * @Route("/save/receipts/info")
     */
    public function saveReceiptsInfo(Request $request)
    {
//        $datePrepare = $request->query->get('datePrepare');
//        $testDate=$this->convertStrToDate($datePrepare);
//        $nextDate=date('Y-m-d',strtotime("+1 day",strtotime($testDate)));
//        dd($testDate,$nextDate);
        $entityManager = $this->getDoctrine()->getManager('default');
        $customEntityManager = $this->getDoctrine()->getManager('custom');

        $sqlParcelTest = "SELECT bill_no FROM receipts";
        $billNoExisted = $customEntityManager->getConnection()->query($sqlParcelTest);
        $dataBillNoExisted = json_decode($this->json($billNoExisted)->getContent(), true);

        $strBillExisted = '';
        foreach ($dataBillNoExisted as $billNo) {
            $strBillExisted .= "'" . $billNo['bill_no'] . "',";
        }
        $billNoTested = rtrim($strBillExisted, ", ");//cut comma at last string

        $sqlPeakRequest = "SELECT bill_no,peak_status,json_send,json_result,item_date FROM peak_prepare_to_send " .
            "WHERE peak_method='receipts' ".
//            "AND (item_date >= DATE('".$testDate."') AND item_date < DATE('".$nextDate."')) " .
            "AND bill_no NOT IN (" . $billNoTested . ") " .
            "ORDER BY recorddate DESC";
//        $sqlPeakRequest = "SELECT bill_no, json_send, json_result FROM peak_prepare_to_send WHERE peak_method='receipts' AND bill_no='89-835-190610152214-275'";
        $billNoToPrepare = $entityManager->getConnection()->query($sqlPeakRequest);
        $dataPeak = json_decode($this->json($billNoToPrepare)->getContent(), true);

        if ($dataPeak == null || $dataPeak == []) {
            $output = ['status' => 'Error No Data'];
        } else {

            foreach ($dataPeak as $item) {
                if ($item['peak_status'] == 'error') {
                    $result = 'Error Peak Not Response';
                    $insertQuery = "INSERT INTO receipts(bill_no, result,record_date) " .
                        "VALUES ('" . $item['bill_no'] . "','" . $result . "',CURRENT_TIMESTAMP())";
                    $customEntityManager->getConnection()->query($insertQuery);
                } else {
                    $dataJsonSend = json_decode($item['json_send'], true);
                    $dataJsonPeak = json_decode($item['json_result'], true);

                    foreach ($dataJsonSend['PeakReceipts']['receipts'] as $itemReceipts) {

                        $code = $itemReceipts['code'];//code
                        $issuedDate = $itemReceipts['issuedDate'];
                        $dueDate = $itemReceipts['dueDate'];
                        $issueDateToDate = $this->convertStrToDate($issuedDate);
                        $dueDateToDate = $this->convertStrToDate($dueDate);

                        $strBillNo = $itemReceipts['tags'][1];
                        $exBillNo = explode("|", $strBillNo);//$exBillNo[1]

                        $merId = explode("-", $exBillNo[1]);//merId[0]

                        $strShopName = $itemReceipts['tags'][3];
                        $exShopName = explode("|", $strShopName);//$exShopName[1]

                        $amount = $itemReceipts['paidPayments']['payments'][0]['amount'];

                        foreach ($itemReceipts['products'] as $product) {
                            $insertProductItem = "INSERT INTO receipts_send_item(bill_no,item_date, product_id, quantity, product_price, send_peak_price, price) VALUES " .
                                "('" . $exBillNo[1] . "','".$issuedDate."','" . $product['productId'] . "'," . $product['quantity'] . "," . $product['productprice'] . "," . $product['peak_price'] . "," . $product['price'] . ")";
                            $customEntityManager->getConnection()->query($insertProductItem);
                        }
                    }

                    if ($dataJsonPeak == '' || !is_array($dataJsonPeak)) {
                        $insertQuery = "INSERT INTO receipts(code, issued_date, bill_no, mer_id, shop_name, amount_send,peak_due_date,record_date) " .
                            "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . ",'" . $dueDateToDate . "',CURRENT_TIMESTAMP())";
                    } elseif ($dataJsonPeak['PeakReceipts']['resCode'] != 200) {
                        $insertQuery = "INSERT INTO receipts(code, issued_date, bill_no,mer_id, shop_name, amount_send,peak_due_date,peak_res_code,peak_res_desc,record_date) " .
                            "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . ",'" . $dueDateToDate . "','" . $dataJsonPeak['PeakReceipts']['resCode'] . "','" . $dataJsonPeak['PeakReceipts']['resDesc'] . "',CURRENT_TIMESTAMP()";
                    } else {
                        foreach ($dataJsonPeak['PeakReceipts']['receipts'] as $itemReponse) {
                            if ($itemReponse['resCode'] != 200) {
                                $insertQuery = "INSERT INTO receipts(code, issued_date, bill_no,mer_id, shop_name, amount_send,peak_due_date,peak_res_code,peak_res_desc,peak_code,peak_desc,record_date) " .
                                    "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . ",'" . $dueDateToDate . "','" . $dataJsonPeak['PeakReceipts']['resCode'] . "','" . $dataJsonPeak['PeakReceipts']['resDesc'] . "','" . $itemReponse['resCode'] . "','" . $itemReponse['resDesc'] . "',CURRENT_TIMESTAMP())";

                            } else {
                                $status = $itemReponse['status'];
                                $paymentAmount = $itemReponse['paymentAmount'];
                                $onlineViewLink = $itemReponse['onlineViewLink'];

                                foreach ($itemReponse['products'] as $productPeak) {
                                    $insertProductPeakItem = "INSERT INTO receipts_peak_item(bill_no,item_date,peak_id, product_id, product_code, quantity, price, discount) VALUES " .
                                        "('" . $exBillNo[1] . "',".$issuedDate.",'" . $productPeak['id'] . "','" . $productPeak['productId'] . "','" . $productPeak['productCode'] . "'," . $productPeak['quantity'] . "," . $productPeak['price'] . "," . $productPeak['discount'] . ")";

                                    $customEntityManager->getConnection()->query($insertProductPeakItem);
                                }
                                $insertQuery = "INSERT INTO receipts(code, issued_date, bill_no,mer_id, shop_name, amount_send,amount_peak,peak_due_date,peak_res_code,peak_res_desc,peak_code,peak_desc,peak_status,peak_online_link,record_date) " .
                                    "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . "," . $paymentAmount . ",'" . $dueDateToDate . "','" . $dataJsonPeak['PeakReceipts']['resCode'] . "','" . $dataJsonPeak['PeakReceipts']['resDesc'] . "','" . $itemReponse['resCode'] . "','" . $itemReponse['resDesc'] . "','" . $status . "','" . $onlineViewLink . "',CURRENT_TIMESTAMP())";

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
     * @Route("/check/receipts/info")
     */
    public function checkReceiptsInfo(Request $request)
    {
//        $entityManager = $this->getDoctrine()->getManager('default');
        $customEntityManager = $this->getDoctrine()->getManager('custom');
        $sqlPeakRequest = "SELECT * FROM receipts where result is null";
        $billNotTest = $customEntityManager->getConnection()->query($sqlPeakRequest);
        $dataExisted = json_decode($this->json($billNotTest)->getContent(), true);

//        dd($dataExisted);
        foreach($dataExisted as $item){
            dd($item);
        }
    }
}

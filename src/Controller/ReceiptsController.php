<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ReceiptsController extends AbstractController
{
    /**
     * @Route("/check/receipts/", name="receipts")
     */
    public function checkReceipts(Request $request)
    {
        $entityManager = $this->getDoctrine()->getManager('default');
        $customEntityManager = $this->getDoctrine()->getManager('custom');

        $sqlPeakRequest = "SELECT bill_no, json_send, json_result " .
            "FROM peak_prepare_to_send " .
            "WHERE peak_method='receipts' " .
            "ORDER BY recorddate DESC";
        $billNoToPrepare = $entityManager->getConnection()->query($sqlPeakRequest);
        $dataPeak = json_decode($this->json($billNoToPrepare)->getContent(), true);

        foreach ($dataPeak as $item) {
//            dd($item['bill_no']);
            $sqlParcelTest = "SELECT bill_no FROM receipts WHERE bill_no='" . $item['bill_no'] . "'";
            $billNoExisted = $customEntityManager->getConnection()->query($sqlParcelTest);
            $dataBillNoExisted = json_decode($this->json($billNoExisted)->getContent(), true);
//            dd($dataBillNoExisted[0]);
            if ($dataBillNoExisted == null || $dataBillNoExisted == '') {
                $dataJsonSend = json_decode($item['json_send'], true);
                $dataJsonPeak = json_decode($item['json_result'], true);

                foreach ($dataJsonSend['PeakReceipts']['receipts'] as $itemReceipts) {

                    $code = $itemReceipts['code'];//code
                    $issuedDate = $itemReceipts['issuedDate'];
                    $changeToDate = $this->convertIssuedToDate($issuedDate);

                    $strBillNo = $itemReceipts['tags'][1];
                    $exBillNo = explode("|", $strBillNo);//$exBillNo[1]

                    $strShopName = $itemReceipts['tags'][3];
                    $exShopName = explode("|", $strShopName);//$exShopName[1]

                    $amount = $itemReceipts['paidPayments']['payments'][0]['amount'];

                }

                if ($dataJsonPeak == '' || !is_array($dataJsonPeak)) {
                    $result = "No JSON Result";
                    $insertQuery = "INSERT INTO receipts(code, issued_date, bill_no, shop_name, amount_send,result,record_date) " .
                        "VALUES ('" . $code . "','" . $changeToDate . "','" . $exBillNo[1] . "','" . $exShopName[1] . "'," . $amount . ",'" . $result . "',CURRENT_TIMESTAMP())";
                } else {
                    $result = "Error Peak Msg";
                    $peakResDescResult = $dataJsonPeak['PeakReceipts']['resDesc'];

                    if ($dataJsonPeak['PeakReceipts']['resCode'] != 200) {
                        $insertQuery = "INSERT INTO receipts(code, issued_date, bill_no, shop_name, amount_send,peak_res_code,peak_res_desc,result,record_date) " .
                            "VALUES ('" . $code . "','" . $changeToDate . "','" . $exBillNo[1] . "','" . $exShopName[1] . "'," . $amount . ",'" . $dataJsonPeak['PeakReceipts']['resCode'] . "','" . $peakResDescResult . "','" . $result . "',CURRENT_TIMESTAMP()";

                    } else {

                        foreach ($dataJsonPeak['PeakReceipts']['receipts'] as $itemReponse) {

                            if ($itemReponse['resCode'] != 200) {
//                            $result="Error Peak Msg";
                                $insertQuery = "INSERT INTO receipts(code, issued_date, bill_no, shop_name, amount_send,peak_res_code,peak_res_desc,peak_code,peak_desc,result,record_date) " .
                                    "VALUES ('" . $code . "','" . $changeToDate . "','" . $exBillNo[1] . "','" . $exShopName[1] . "'," . $amount . ",'" . $dataJsonPeak['PeakReceipts']['resCode'] . "','" . $peakResDescResult . "','" . $itemReponse['resCode'] . "','" . $itemReponse['resDesc'] . "','" . $result . "',CURRENT_TIMESTAMP())";

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
                                $insertQuery = "INSERT INTO receipts(code, issued_date, bill_no, shop_name, amount_send,amount_peak,peak_res_code,peak_res_desc,peak_code,peak_desc,peak_status,peak_online_link,result,record_date) " .
                                    "VALUES ('" . $code . "','" . $changeToDate . "','" . $exBillNo[1] . "','" . $exShopName[1] . "'," . $amount . "," . $paymentAmount . ",'" . $dataJsonPeak['PeakReceipts']['resCode'] . "','" . $peakResDescResult . "','" . $itemReponse['resCode'] . "','" . $itemReponse['resDesc'] . "','" . $status . "','" . $onlineViewLink . "','" . $result . "',CURRENT_TIMESTAMP())";

                            }

                        }
                    }

                }

                $customEntityManager->getConnection()->query($insertQuery);
            } else {

                $output=['status'=>'test'];
            }
        }
        $sqlBillNoError = "SELECT bill_no,peak_res_code,peak_res_desc,peak_code,peak_desc,peak_status,peak_online_link " .
            "FROM receipts " .
            "WHERE result!='Correct'" .
            "ORDER BY record_date ASC";
        $billNoError = $customEntityManager->getConnection()->query($sqlBillNoError);
        $dataBillNoError = json_decode($this->json($billNoError)->getContent(), true);

        if ($dataBillNoError == []) {
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

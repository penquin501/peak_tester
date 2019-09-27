<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class InvoiceController extends AbstractController
{
    /**
     * @Route("/save/invoice", name="save_invoice")
     */
    public function saveInvoiceInfo(Request $request)
    {
        $datePrepare = $request->query->get('datePrepare');
        $testDate = $this->convertStrToDate($datePrepare);
        $nextDate = date('Y-m-d', strtotime("+1 day", strtotime($testDate)));

        $entityManager = $this->getDoctrine()->getManager('default');
        $customEntityManager = $this->getDoctrine()->getManager('custom');

        $sqlParcelTest = "SELECT bill_no FROM invoice";
        $billNoExisted = $customEntityManager->getConnection()->query($sqlParcelTest);
        $dataBillNoExisted = json_decode($this->json($billNoExisted)->getContent(), true);

        $strBillExisted = '';
        foreach ($dataBillNoExisted as $billNo) {
            $strBillExisted .= "'" . $billNo['bill_no'] . "',";
        }
        $billNoTested = rtrim($strBillExisted, ", ");//cut comma at last string

        $sqlPeakRequest = "SELECT bill_no,peak_status,json_send,json_result " .
            "FROM peak_prepare_to_send " .
            "WHERE peak_method='invoice' " .
            "AND (item_date>=DATE('" . $testDate . "') and item_date<DATE('" . $nextDate . "')) " .
            "AND bill_no NOT IN (" . $billNoTested . ")" .
            "ORDER BY recorddate DESC";
        $billNoToPrepare = $entityManager->getConnection()->query($sqlPeakRequest);
        $dataPeak = json_decode($this->json($billNoToPrepare)->getContent(), true);

        if ($dataPeak == null || $dataPeak == []) {
            $output = ['status' => 'Error No Data'];
        } else {
            $amount=0;
            foreach ($dataPeak as $item) {
                if ($item['peak_status'] == 'error') {
                    $result = 'Error Peak Not Response';
                    $insertQuery = "INSERT INTO invoice(bill_no, result,record_date) " .
                        "VALUES ('" . $item['bill_no'] . "','" . $result . "',CURRENT_TIMESTAMP())";
                    $customEntityManager->getConnection()->query($insertQuery);
                } else {
                    $dataJsonSend = json_decode($item['json_send'], true);
                    $dataJsonPeak = json_decode($item['json_result'], true);
                    foreach ($dataJsonSend['PeakInvoices']['invoices'] as $itemInvoice) {
//                        dd($itemInvoice);
                        $code = $itemInvoice['code'];//code
                        $issuedDate = $itemInvoice['issuedDate'];
                        $dueDate = $itemInvoice['dueDate'];
                        $issueDateToDate = $this->convertStrToDate($issuedDate);
                        $dueDateToDate = $this->convertStrToDate($dueDate);

                        $strBillNo = $itemInvoice['tags'][1];
                        $exBillNo = explode("|", $strBillNo);//$exBillNo[1]

                        $merId = explode("-", $exBillNo[1]);//$merId[0]

                        $strShopName = $itemInvoice['tags'][3];
                        $exShopName = explode("|", $strShopName);//$exShopName[1]

                        foreach ($itemInvoice['products'] as $product) {
//                            dd($product);
                            $insertProductItem = "INSERT INTO invoice_send_item(bill_no, item_date, product_id, quantity, price) VALUES " .
                                "('" . $exBillNo[1] . "','" . $issueDateToDate . "','" . $product['productId'] . "'," . $product['quantity'] . "," . $product['price'] . ")";
                            $customEntityManager->getConnection()->query($insertProductItem);
                            $amount+=$product['price'];
                        }

                    }
//                    dd($amount);
                    if ($dataJsonPeak == '' || !is_array($dataJsonPeak)) {
                        $insertQuery = "INSERT INTO invoice(code, issued_date, bill_no, mer_id, shop_name, amount_send,peak_due_date,record_date) " .
                            "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . ",'" . $dueDateToDate . "',CURRENT_TIMESTAMP())";
                    } elseif ($dataJsonPeak['PeakInvoices']['resCode'] != 200) {
//                        $peakResDescResult = $dataJsonPeak['PeakExpenses']['resDesc'];
                        $insertQuery = "INSERT INTO invoice(code, issued_date, bill_no,mer_id, shop_name, amount_send,peak_due_date,peak_res_code,peak_res_desc,record_date) " .
                            "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . ",'" . $dueDateToDate . "','" . $dataJsonPeak['PeakInvoices']['resCode'] . "','" . $dataJsonPeak['PeakInvoices']['resDesc'] . "',CURRENT_TIMESTAMP()";
                    } else {
                        foreach ($dataJsonPeak['PeakInvoices']['invoices'] as $itemReponse) {
//                            dd($itemReponse);
                            if ($itemReponse['resCode'] != 200) {
                                $insertQuery = "INSERT INTO invoice(code, issued_date, bill_no,mer_id, shop_name, amount_send,peak_due_date,peak_res_code,peak_res_desc,peak_code,peak_desc,record_date) " .
                                    "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . ",'" . $dueDateToDate . "','" . $dataJsonPeak['PeakInvoices']['resCode'] . "','" . $dataJsonPeak['PeakInvoices']['resDesc'] . "','" . $itemReponse['resCode'] . "','" . $itemReponse['resDesc'] . "',CURRENT_TIMESTAMP())";
                            } else {

                                $status = $itemReponse['status'];
//                                $paymentAmount = $itemReponse['paymentAmount'];
                                $preTaxAmount = $itemReponse['preTaxAmount'];
                                $vatAmount = $itemReponse['vatAmount'];
                                $paymentAmount = $itemReponse['netAmount'];
                                $onlineViewLink = $itemReponse['onlineViewLink'];

                                foreach ($itemReponse['products'] as $productPeak) {
//                                    dd($productPeak);
                                    $insertProductPeakItem = "INSERT INTO invoice_peak_item(bill_no, item_date, peak_id, product_id, product_code, quantity, price, discount) VALUES " .
                                        "('" . $exBillNo[1] . "'," . $issuedDate . ",'" . $productPeak['id'] . "','" . $productPeak['productId'] . "','".$productPeak['productCode']."'," . $productPeak['quantity'] . "," . $productPeak['price'] . "," . $productPeak['discount'] . ")";

                                    $customEntityManager->getConnection()->query($insertProductPeakItem);
                                }
                                $insertQuery = "INSERT INTO invoice(code, issued_date, bill_no, mer_id, shop_name, amount_send, pre_tax_amount, vat_amount, amount_peak, peak_due_date, peak_res_code, peak_res_desc, peak_code, peak_desc, peak_status, peak_online_link, record_date) " .
                                    "VALUES ('" . $code . "','" . $issueDateToDate . "','" . $exBillNo[1] . "'," . $merId[0] . ",'" . $exShopName[1] . "'," . $amount . ",".$preTaxAmount.",".$vatAmount."," . $paymentAmount . ",'" . $dueDateToDate . "','" . $dataJsonPeak['PeakInvoices']['resCode'] . "','" . $dataJsonPeak['PeakInvoices']['resDesc'] . "','" . $itemReponse['resCode'] . "','" . $itemReponse['resDesc'] . "','" . $status . "','" . $onlineViewLink . "',CURRENT_TIMESTAMP())";

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
}

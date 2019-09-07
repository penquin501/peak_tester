<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class ExpensesController extends AbstractController
{
    /**
     * @Route("/expenses/check/billing", name="expenses")
     */
    public function expensesCheckBilling(EntityManagerInterface $entityManager, Request $request)
    {
        $entityManager = $this->getDoctrine()->getManager('default');
        $customEntityManager = $this->getDoctrine()->getManager('custom');

        $sqlPeakRequest = "SELECT bill_no, json_send, json_result " .
                          "FROM peak_prepare_to_send " .
                          "WHERE peak_method='expenses' AND DATE(recorddate)>='2019-09-04'";
        $billNoToPrepare = $entityManager->getConnection()->query($sqlPeakRequest);
        $dataPeak = json_decode($this->json($billNoToPrepare)->getContent(), true);

//        dd($dataPeak);
        foreach ($dataPeak as $item) {
            $dataJsonSend = json_decode($item['json_send'], true);
            $dataJsonPeak = json_decode($item['json_result'], true);
//            dd($dataJsonPeak['PeakExpenses']['expenses']);
            foreach ($dataJsonSend['PeakExpenses']['expenses'] as $itemExpenses) {
                $code = $itemExpenses['code'];//code
                $issuedDate = $itemExpenses['issuedDate'];

                $strBillNo = $itemExpenses['tags'][1];
                $exBillNo = explode("|", $strBillNo);//billNo
//                dd($exBillNo[1]);
                $strShopName = $itemExpenses['tags'][3];
                $exShopName = explode("|", $strShopName);//shopName
//                dd($exShopName[1]);
                $strDeliveryFee = $itemExpenses['tags'][4];
                $exDeliveryFee = explode("|", $strDeliveryFee);//DeliveryFee
                //dd($exDeliveryFee[1]);
                $accountCode = $itemExpenses['paidPayments']['payments'][1]['accountCode'];
                $amount = $itemExpenses['paidPayments']['payments'][1]['amount'];

            }
            foreach ($dataJsonPeak['PeakExpenses']['expenses'] as $itemReponse) {
//                dd($itemReponse['resCode']);

                if($itemReponse['resCode']!=200){


                } else {
                    $status = $itemReponse['status'];
                    $preTaxAmount = $itemReponse['preTaxAmount'];
                    $vatAmount = $itemReponse['vatAmount'];
                    $netAmount = $itemReponse['netAmount'];
                    $paymentAmount = $itemReponse['paymentAmount'];
                    $onlineViewLink = $itemReponse['onlineViewLink'];
                    $resCode = $itemReponse['resCode'];
                }

            }
//            $insertSql="";
            dd($exBillNo[1],$exShopName[1],$exDeliveryFee[1]);

        }
        $output = ['billNo' => $amount];
        return $this->json($output);
    }
}

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
    public function expensesCheckBilling(EntityManagerInterface $entityManager,Request $request)
    {
        $entityManager = $this->getDoctrine()->getManager('default');
        $customEntityManager = $this->getDoctrine()->getManager('custom');
        $output=[];
        $sqlPeakRequest="SELECT bill_no, json_send, json_result ".
                        "FROM peak_prepare_to_send ".
                        "WHERE peak_method='expenses' AND DATE(recorddate)>='2019-09-04'";
        $billNoToPrepare=$entityManager->getConnection()->query($sqlPeakRequest);
        $dataPeak=json_decode($this->json($billNoToPrepare)->getContent(),true);

//        dd($dataPeak);
        foreach ($dataPeak as $item) {
            $dataJsonSend=json_decode($item['json_send'],true);
            dd($dataJsonSend['PeakExpenses']['expenses']);
            $sqlMerchantBilling = "SELECT mb.parcel_bill_no,SUM(mb.payment_amt) as sumPayAmt,SUM(mb.payment_discount) as sumDiscount,Sum(mb.transportprice) as sumTransPrice,SUM(md.productcost) as sumCost " .
                "FROM merchant_billing mb " .
                "JOIN merchant_billing_detail md " .
                "ON mb.takeorderby=md.takeorderby and mb.payment_invoice=md.payment_invoice " .
                "WHERE mb.parcel_bill_no = '" . $item['bill_no']."'";
            $merchantBillingInfo=$entityManager->getConnection()->query($sqlMerchantBilling);
            $dataMerchantBilling=json_decode($this->json($merchantBillingInfo)->getContent(),true);
            dd($dataMerchantBilling);
        }

//        $sql="SELECT bill_no,json_send,json_result FROM peak_prepare_to_send WHERE peak_method='expenses' AND Date(recorddate) >= '2019-09-01'";
//        $id = $request->query->get('id');

//        $query="SELECT T01_AFA_BENEFIT_FNC_GET_TODAY_SALE_AMT(A.id) AS T01_TOTAL_SALE_AMT_TODAY FROM VW01_AFA_BENEFIT A WHERE A.id =".$id;


        return $this->json($output);
    }
}

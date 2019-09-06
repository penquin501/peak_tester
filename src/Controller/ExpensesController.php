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
        $customerEntityManager = $this->getDoctrine()->getManager('customer');
        $output=[];
        $sqlPeakRequest="SELECT bill_no, json_send, json_result ".
                        "FROM peak_prepare_to_send ".
                        "WHERE peak_method='expenses' AND DATE(recorddate)>='2019-09-04'";
        $billNoToPrepare=$entityManager->getConnection()->query($sqlPeakRequest);
        $dataPeak=json_decode($this->json($billNoToPrepare)->getContent(),true);

//        dd($data);
        foreach ($dataPeak as $item) {
//            dd($item['bill_no']);
            $sqlMerchantBilling = "SELECT mb.parcel_bill_no,mb.payment_amt,mb.payment_discount,mb.transportprice,md.productcost " .
                "FROM merchant_billing mb " .
                "JOIN merchant_billing_detail md " .
                "ON mb.takeorderby=md.takeorderby and mb.payment_invoice=md.payment_invoice " .
                "WHERE mb.parcel_bill_no = " . $item['bill_no'];
            $output=$entityManager->getConnection()->query($sqlMerchantBilling);

        }

//        $sql="SELECT bill_no,json_send,json_result FROM peak_prepare_to_send WHERE peak_method='expenses' AND Date(recorddate) >= '2019-09-01'";
//        $id = $request->query->get('id');

//        $query="SELECT T01_AFA_BENEFIT_FNC_GET_TODAY_SALE_AMT(A.id) AS T01_TOTAL_SALE_AMT_TODAY FROM VW01_AFA_BENEFIT A WHERE A.id =".$id;


        return $this->json($billNoToPrepare);
    }
}

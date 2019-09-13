<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PaidPaymentsController extends AbstractController
{
    /**
     * @Route("/check/paid/payments", name="paid_payments")
     */
    public function checkPaidPayments(Request $request)
    {
        $entityManager = $this->getDoctrine()->getManager('default');
        $customEntityManager = $this->getDoctrine()->getManager('custom');
        $sqlPeakRequest = "SELECT bill_no, json_send, json_result " .
            "FROM peak_prepare_to_send_2 " .
            "WHERE peak_method='paidpayments' " .
            "ORDER BY recorddate DESC";
        $billNoToPrepare = $entityManager->getConnection()->query($sqlPeakRequest);
        $dataPeak = json_decode($this->json($billNoToPrepare)->getContent(), true);
        foreach ($dataPeak as $item) {
            $dataJsonSend = json_decode($item['json_send'], true);
            $dataJsonPeak = json_decode($item['json_result'], true);
dd($dataJsonSend['PeakPaidPayments']['payments']);
            foreach ($dataJsonSend['PeakPaidPayments']['payments'] as $itemPaidPayment) {

            }

        }

        return $this->json($output);
    }
}

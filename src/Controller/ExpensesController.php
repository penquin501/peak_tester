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
        $sql="SELECT bill_no,json_send,json_result FROM peak_prepare_to_send WHERE peak_method='expenses' AND Date(recorddate) >= '2019-09-01'";
//        $id = $request->query->get('id');

//        $query="SELECT T01_AFA_BENEFIT_FNC_GET_TODAY_SALE_AMT(A.id) AS T01_TOTAL_SALE_AMT_TODAY FROM VW01_AFA_BENEFIT A WHERE A.id =".$id;

        $output=$customerEntityManager->getConnection()->query($sql);
        return $this->json($output);
//        $sql=
//        return $this->render('expenses/index.html.twig', [
//            'controller_name' => 'ExpensesController',
//        ]);
    }
}

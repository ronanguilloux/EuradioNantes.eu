<?php

namespace RadioSolution\SiteBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class HomeController extends Controller
{
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $query = $em
            ->createQuery("SELECT p FROM ApplicationSonataNewsBundle:Post p WHERE p.enabled = 1 AND p.publicationDateStart <= :now ORDER BY p.position DESC, p.publicationDateStart DESC")
            ->setParameter('now', new \DateTime())
            ->setMaxResults(18)
        ;

        $posts = $query->getResult();

        return $this->render('SiteBundle:Home:index.html.twig', compact('posts'));
    }
}

<?php

namespace Keboola\FacebookAdsExtractorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('KeboolaFacebookAdsExtractorBundle:Default:index.html.twig', array('name' => $name));
    }
}

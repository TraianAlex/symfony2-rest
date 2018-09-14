<?php

namespace AppBundle\Controller\Api;

use AppBundle\Api\ApiProblem;
use AppBundle\Api\ApiProblemException;
use AppBundle\Form\UpdateProgrammerType;
use AppBundle\Controller\BaseController;
use AppBundle\Entity\Programmer;
use AppBundle\Form\ProgrammerType;
use AppBundle\Pagination\PaginatedCollection;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProgrammerController extends BaseController
{
    /**
     * @Route("/api/programmers")
     * @Method("POST")
     */
    public function newAction(Request $request)
    {
        $programmer = new Programmer();
        $form = $this->createForm(new ProgrammerType(), $programmer);
        $this->processForm($request, $form);
        if ($form->isSubmitted() && !$form->isValid()) {
            //header('Content-Type: cli');
            //dump((string) $form->getErrors(true, false));die;
            $this->throwApiProblemValidationException($form);
        }
        $programmer->setUser($this->findUserByUsername('weaverryan'));

        $em = $this->getDoctrine()->getManager();
        $em->persist($programmer);
        $em->flush();

        $response = $this->createApiResponse($programmer, 201);
        $programmerUrl = $this->generateUrl(
            'api_programmers_show',
            ['nickname' => $programmer->getNickname()]
        );
        $response->headers->set('Location', $programmerUrl);

        return $response;
    }

    /**
     * @Route("/api/programmers/{nickname}", name="api_programmers_show")
     * @Method("GET")
     */
    public function showAction($nickname)
    {
        $programmer = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findOneByNickname($nickname);
        if (!$programmer) {
            throw $this->createNotFoundException(sprintf(
                'No programmer found with nickname "%s"',
                $nickname
            ));
        }
        $response = $this->createApiResponse($programmer, 200);
        return $response;
    }

    /**
     * @Route("/api/programmers", name="api_programmers_collection")
     * @Method("GET")
     */
    public function listAction(Request $request)
    {
        //$page = $request->query->get('page', 1);
        $filter = $request->query->get('filter');

        $qb = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findAllQueryBuilder($filter);

//        $adapter = new DoctrineORMAdapter($qb);
//        $pagerfanta = new Pagerfanta($adapter);
//        $pagerfanta->setMaxPerPage(10);
//        $pagerfanta->setCurrentPage($page);

//        $programmers = [];
//        foreach ($pagerfanta->getCurrentPageResults() as $result) {
//            $programmers[] = $result;
//        }
        //$data = ['programmers' => $programmers];
        //$response = $this->createApiResponse($data, 200);

//        $response = $this->createApiResponse([
//            'total' => $pagerfanta->getNbResults(),
//            'count' => count($programmers),
//            'programmers' => $programmers,
//        ], 200);
//        $paginatedCollection = new PaginatedCollection($programmers, $pagerfanta->getNbResults());
//
//        $route = 'api_programmers_collection';
//        $routeParams = array();
//        $createLinkUrl = function($targetPage) use ($route, $routeParams) {
//            return $this->generateUrl($route, array_merge(
//                $routeParams,
//                array('page' => $targetPage)
//            ));
//        };
//
//        $paginatedCollection->addLink('self', $createLinkUrl($page));
//        $paginatedCollection->addLink('first', $createLinkUrl(1));
//        $paginatedCollection->addLink('last', $createLinkUrl($pagerfanta->getNbPages()));
//        if ($pagerfanta->hasNextPage()) {
//            $paginatedCollection->addLink('next', $createLinkUrl($pagerfanta->getNextPage()));
//        }
//        if ($pagerfanta->hasPreviousPage()) {
//            $paginatedCollection->addLink('prev', $createLinkUrl($pagerfanta->getPreviousPage()));
//        }
        $paginatedCollection = $this->get('pagination_factory')
            ->createCollection($qb, $request, 'api_programmers_collection');

        $response = $this->createApiResponse($paginatedCollection, 200);

        return $response;
    }

    /**
     * @Route("/api/programmers/{nickname}")
     * @Method({"PUT", "PATCH"})
     */
    public function updateAction($nickname, Request $request)
    {
        $programmer = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findOneByNickname($nickname);
        if (!$programmer) {
            throw $this->createNotFoundException(sprintf(
                'No programmer found with nickname "%s"',
                $nickname
            ));
        }

        $form = $this->createForm(new UpdateProgrammerType(), $programmer);
        $this->processForm($request, $form);
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->throwApiProblemValidationException($form);
        }
        $em = $this->getDoctrine()->getManager();
        $em->persist($programmer);
        $em->flush();
        $response = $this->createApiResponse($programmer, 200);
        return $response;
    }

    /**
     * @Route("/api/programmers/{nickname}")
     * @Method("DELETE")
     */
    public function deleteAction($nickname)
    {
        $programmer = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findOneByNickname($nickname);

        if ($programmer) {
            // debated point: should we 404 on an unknown nickname?
            // or should we just return a nice 204 in all cases?
            // we're doing the latter
            $em = $this->getDoctrine()->getManager();
            $em->remove($programmer);
            $em->flush();
        }

        return new Response(null, 204);
    }

    private function processForm(Request $request, Form $form)
    {
        $data = json_decode($request->getContent(), true);
        if ($data === null) {
            $apiProblem = new ApiProblem(400, ApiProblem::TYPE_INVALID_REQUEST_BODY_FORMAT);
            //throw new HttpException(400, 'Invalid JSON body!');
            throw new ApiProblemException($apiProblem);
        }
        $clearMissing = $request->getMethod() != 'PATCH';
        $form->submit($data, $clearMissing);
    }

    private function getErrorsFromForm(FormInterface $form)
    {
        $errors = array();
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }
        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsFromForm($childForm)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }
        return $errors;
    }

    private function throwApiProblemValidationException(FormInterface $form)
    {
        $errors = $this->getErrorsFromForm($form);

        $apiProblem = new ApiProblem(
            400,
            ApiProblem::TYPE_VALIDATION_ERROR
        );
        $apiProblem->set('errors', $errors);

        //return new JsonResponse($data, 400);
//        $response = new JsonResponse($apiProblem->toArray(), $apiProblem->getStatusCode());
//        $response->headers->set('Content-Type', 'application/problem+json');
//        return $response;
        throw new ApiProblemException($apiProblem);
    }
}
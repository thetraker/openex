<?php

namespace APIBundle\Controller;

use APIBundle\Entity\DryinjectStatus;
use APIBundle\Entity\Subaudience;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use APIBundle\Entity\Audience;
use APIBundle\Entity\Exercise;
use APIBundle\Form\Type\InjectType;
use APIBundle\Entity\InjectStatus;
use APIBundle\Entity\Event;
use APIBundle\Entity\Incident;
use APIBundle\Entity\Inject;
use APIBundle\Entity\Dryinject;

class InjectController extends Controller
{
    /**
     * @ApiDoc(
     *    description="List injects for the worker"
     * )
     * @Rest\View(serializerGroups={"inject"})
     * @Rest\Get("/injects_all")
     */
    public function getAllInjectsAction(Request $request)
    {
        if (!$this->get('security.token_storage')->getToken()->getUser()->isAdmin())
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Access Denied.");

        $em = $this->get('doctrine.orm.entity_manager');
        $exercises = $em->getRepository('APIBundle:Exercise')->findAll();
        /* @var $exercises Exercise[] */

        $injects = array();
        foreach ($exercises as $exercise) {
            $events = $em->getRepository('APIBundle:Event')->findBy(['event_exercise' => $exercise]);
            /* @var $events Event[] */

            foreach ($events as $event) {
                $incidents = $em->getRepository('APIBundle:Incident')->findBy(['incident_event' => $event]);
                /* @var $incidents Incident[] */

                foreach ($incidents as $incident) {
                    $incidentInjects = $em->getRepository('APIBundle:Inject')->findBy(['inject_incident' => $incident]);
                    foreach ($incidentInjects as &$incidentInject) {
                        $incidentInject->setInjectEvent($event->getEventId());
                        $incidentInject->setInjectExercise($exercise->getExerciseId());
                    }
                    $injects = array_merge($injects, $incidentInjects);
                }
            }
        }

        foreach ($injects as &$inject) {
            $inject->sanitizeUser();
            $inject->computeUsersNumber();
        }

        return $injects;
    }

    /**
     * @ApiDoc(
     *    description="List injects for the worker"
     * )
     *
     * @Rest\Get("/injects")
     */
    public function getInjectsAction(Request $request)
    {
        if (!$this->get('security.token_storage')->getToken()->getUser()->isAdmin())
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Access Denied.");

        $em = $this->get('doctrine.orm.entity_manager');

        $injects = array();
        $dateStart = new \DateTime();
        $dateStart->modify('-60 minutes');
        $dateEnd = new \DateTime();
        $exercises = $em->getRepository('APIBundle:Exercise')->findBy(['exercise_canceled' => 0]);
        /* @var $exercises Exercise[] */
        foreach ($exercises as $exercise) {
            $events = $em->getRepository('APIBundle:Event')->findBy(['event_exercise' => $exercise]);
            /* @var $events Event[] */
            foreach ($events as $event) {
                $incidents = $em->getRepository('APIBundle:Incident')->findBy(['incident_event' => $event]);
                /* @var $incidents Incident[] */

                foreach ($incidents as $incident) {
                    $incidentInjects = $em->getRepository('APIBundle:Inject')->createQueryBuilder('i')
                        ->leftJoin('i.inject_status', 's')
                        ->where('s.status_inject = i.inject_id')
                        ->andWhere('s.status_name is NULL')
                        ->andWhere('i.inject_enabled = true')
                        ->andWhere('i.inject_type != \'other\'')
                        ->andWhere('i.inject_incident = :incident')
                        ->andWhere('i.inject_date BETWEEN :start AND :end')
                        ->orderBy('i.inject_date', 'ASC')
                        ->setParameter('incident', $incident->getIncidentId())
                        ->setParameter('start', $dateStart)
                        ->setParameter('end', $dateEnd)
                        ->getQuery()
                        ->getResult();
                    // enrich injects
                    foreach ($incidentInjects as &$incidentInject) {
                        /* @var $incidentInject Inject */
                        $incidentInject->setInjectExercise($exercise);
                        $incidentInject->setInjectHeader($exercise->getExerciseMessageHeader());
                        $incidentInject->setInjectFooter($exercise->getExerciseMessageFooter());
                    }
                    $injects = array_merge($injects, $incidentInjects);
                }
            }
        }

        $output = array();
        foreach ($injects as $inject) {
            $data = array();
            $data['context']['id'] = $inject->getInjectId();
            $data['context']['type'] = $inject->getInjectType();
            $data['context']['callback_url'] = $this->getParameter('protocol') . '://' . $request->getHost() . '/api/injects/' . $inject->getInjectId() . '/status';
            $data['data'] = json_decode($inject->getInjectContent(), true);
            $data['data']['content_header'] = $inject->getInjectHeader();
            $data['data']['content_footer'] = $inject->getInjectFooter();
            $data['data']['users'] = array();

            // list all audiences
            if ($inject->getInjectAllAudiences() == true) {
                foreach ($inject->getInjectExercise()->getExerciseAudiences() as $audience) {
                    if ($audience->getAudienceEnabled() == true) {
                        // list subaudiences of the audience
                        foreach ($audience->getAudienceSubaudiences() as $subaudience) {
                            if ($subaudience->getSubaudienceEnabled() == true) {
                                // list all users of the subaudience
                                foreach ($subaudience->getSubaudienceUsers() as $user) {
                                    if (array_search($user->getUserEmail(), array_column($data['data']['users'], 'user_email')) === false) {
                                        $userData = array();
                                        $userData['user_firstname'] = $user->getUserFirstname();
                                        $userData['user_lastname'] = $user->getUserLastname();
                                        $userData['user_email'] = $user->getUserEmail();
                                        $userData['user_email2'] = $user->getUserEmail2();
                                        $userData['user_phone'] = $user->getUserPhone();
                                        $userData['user_phone2'] = $user->getUserPhone2();
                                        $userData['user_phone3'] = $user->getUserPhone3();
                                        $userData['user_pgp_key'] = base64_encode($user->getUserPgpKey());
                                        $userData['user_organization'] = array();
                                        $userData['user_organization']['organization_name'] = $user->getUserOrganization()->getOrganizationName();
                                        $data['data']['users'][] = $userData;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($inject->getInjectAudiences() as $audience) {
                    /* @var $audience Audience */
                    if ($audience->getAudienceEnabled() == true) {
                        // list subaudiences of the audience
                        foreach ($audience->getAudienceSubaudiences() as $subaudience) {
                            if ($subaudience->getSubaudienceEnabled() == true) {
                                // list all users of the subaudience
                                foreach ($subaudience->getSubaudienceUsers() as $user) {
                                    if (array_search($user->getUserEmail(), array_column($data['data']['users'], 'user_email')) === false) {
                                        $userData = array();
                                        $userData['user_firstname'] = $user->getUserFirstname();
                                        $userData['user_lastname'] = $user->getUserLastname();
                                        $userData['user_email'] = $user->getUserEmail();
                                        $userData['user_email2'] = $user->getUserEmail2();
                                        $userData['user_phone'] = $user->getUserPhone();
                                        $userData['user_phone2'] = $user->getUserPhone2();
                                        $userData['user_phone3'] = $user->getUserPhone3();
                                        $userData['user_pgp_key'] = base64_encode($user->getUserPgpKey());
                                        $userData['user_organization'] = array();
                                        $userData['user_organization']['organization_name'] = $user->getUserOrganization()->getOrganizationName();
                                        $data['data']['users'][] = $userData;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // list subaudiences
            foreach ($inject->getInjectSubaudiences() as $subaudience) {
                /* @var $subaudience Subaudience */
                if ($subaudience->getSubaudienceEnabled() == true) {
                    // list all users of the subaudience
                    foreach ($subaudience->getSubaudienceUsers() as $user) {
                        if (array_search($user->getUserEmail(), array_column($data['data']['users'], 'user_email')) === false) {
                            $userData = array();
                            $userData['user_firstname'] = $user->getUserFirstname();
                            $userData['user_lastname'] = $user->getUserLastname();
                            $userData['user_email'] = $user->getUserEmail();
                            $userData['user_email2'] = $user->getUserEmail2();
                            $userData['user_phone'] = $user->getUserPhone();
                            $userData['user_phone2'] = $user->getUserPhone2();
                            $userData['user_phone3'] = $user->getUserPhone3();
                            $userData['user_pgp_key'] = base64_encode($user->getUserPgpKey());
                            $userData['user_organization'] = array();
                            $userData['user_organization']['organization_name'] = $user->getUserOrganization()->getOrganizationName();
                            $data['data']['users'][] = $userData;
                        }
                    }
                }
            }

            if ($inject->getInjectExercise()->getExerciseAnimationGroup() != null) {
                foreach ($inject->getInjectExercise()->getExerciseAnimationGroup()->getGroupUsers() as $user) {
                    if (array_search($user->getUserEmail(), array_column($data['data']['users'], 'user_email')) === false) {
                        $userData = array();
                        $userData['user_firstname'] = $user->getUserFirstname();
                        $userData['user_lastname'] = $user->getUserLastname();
                        $userData['user_email'] = $user->getUserEmail();
                        $userData['user_email2'] = $user->getUserEmail2();
                        $userData['user_phone'] = $user->getUserPhone();
                        $userData['user_phone2'] = $user->getUserPhone2();
                        $userData['user_phone3'] = $user->getUserPhone3();
                        $userData['user_pgp_key'] = base64_encode($user->getUserPgpKey());
                        $userData['user_organization'] = array();
                        $userData['user_organization']['organization_name'] = $user->getUserOrganization()->getOrganizationName();
                        $data['data']['users'][] = $userData;
                    }
                }
            }

            $output[] = $data;
        }

        $dryinjects = $em->getRepository('APIBundle:Dryinject')->createQueryBuilder('i')
            ->leftJoin('i.dryinject_status', 's')
            ->where('s.status_dryinject = i.dryinject_id')
            ->andWhere('s.status_name is NULL')
            ->andWhere('i.dryinject_type != \'other\'')
            ->andWhere('i.dryinject_date BETWEEN :start AND :end')
            ->orderBy('i.dryinject_date', 'ASC')
            ->setParameter('start', $dateStart)
            ->setParameter('end', $dateEnd)
            ->getQuery()
            ->getResult();

        foreach ($dryinjects as $dryinject) {
            /* @var $dryinject Dryinject */
            $data = array();
            $data['context']['id'] = $dryinject->getDryinjectId();
            $data['context']['type'] = $dryinject->getDryinjectType();
            $data['context']['callback_url'] = $this->getParameter('protocol') . '://' . $request->getHost() . '/api/dryinjects/' . $dryinject->getDryinjectId() . '/status';
            $data['data'] = json_decode($dryinject->getDryinjectContent(), true);
            $data['data']['content_header'] = $dryinject->getDryinjectDryrun()->getDryrunExercise()->getExerciseMessageHeader();
            $data['data']['content_footer'] = $dryinject->getDryinjectDryrun()->getDryrunExercise()->getExerciseMessageFooter();
            $data['data']['users'] = array();

            if ($dryinject->getDryinjectDryrun()->getDryrunExercise()->getExerciseAnimationGroup() != null) {
                foreach ($dryinject->getDryinjectDryrun()->getDryrunExercise()->getExerciseAnimationGroup()->getGroupUsers() as $user) {
                    if (array_search($user->getUserEmail(), array_column($data['data']['users'], 'user_email')) === false) {
                        $userData = array();
                        $userData['user_firstname'] = $user->getUserFirstname();
                        $userData['user_lastname'] = $user->getUserLastname();
                        $userData['user_email'] = $user->getUserEmail();
                        $userData['user_email2'] = $user->getUserEmail2();
                        $userData['user_phone'] = $user->getUserPhone();
                        $userData['user_phone2'] = $user->getUserPhone2();
                        $userData['user_phone3'] = $user->getUserPhone3();
                        $userData['user_pgp_key'] = base64_encode($user->getUserPgpKey());
                        $userData['user_organization'] = array();
                        $userData['user_organization']['organization_name'] = $user->getUserOrganization()->getOrganizationName();
                        $data['data']['users'][] = $userData;
                    }
                }
            }

            $output[] = $data;
        }

        return new Response(json_encode($output));
    }

    /**
     * @ApiDoc(
     *    description="Update the status of an inject",
     * )
     * @Rest\View(serializerGroups={"injectStatus"})
     * @Rest\Post("/injects/{inject_id}/status")
     */
    public function updateInjectStatusAction(Request $request)
    {
        if (!$this->get('security.token_storage')->getToken()->getUser()->isAdmin())
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Access Denied.");

        $em = $this->get('doctrine.orm.entity_manager');
        $inject = $em->getRepository('APIBundle:Inject')->find($request->get('inject_id'));
        /* @var $inject Inject */

        if (empty($inject)) {
            return $this->injectNotFound();
        }

        /** @var InjectStatus $status */
        $status = $inject->getInjectStatus();
        $status->setStatusName($request->request->get('status'));
        $status->setStatusMessage(json_encode($request->request->get('message')));
        $status->setStatusExecution($request->request->get('execution'));
        $status->setStatusDate(new \DateTime());

        $em->persist($status);
        $em->flush();

        return $status;
    }

    /**
     * @ApiDoc(
     *    description="Update the status of an dryinject",
     * )
     * @Rest\View(serializerGroups={"dryinjectStatus"})
     * @Rest\Post("/dryinjects/{dryinject_id}/status")
     */
    public function updateDryinjectStatusAction(Request $request)
    {
        if (!$this->get('security.token_storage')->getToken()->getUser()->isAdmin())
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Access Denied.");

        $em = $this->get('doctrine.orm.entity_manager');
        $dryinject = $em->getRepository('APIBundle:Dryinject')->find($request->get('dryinject_id'));
        /* @var $dryinject Dryinject */

        if (empty($dryinject)) {
            return $this->dryinjectNotFound();
        }

        /** @var DryinjectStatus $status */
        $status = $dryinject->getDryinjectStatus();
        $status->setStatusName($request->request->get('status'));
        $status->setStatusMessage(json_encode($request->request->get('message')));
        $status->setStatusExecution($request->request->get('execution'));
        $status->setStatusDate(new \DateTime());

        $em->persist($status);
        $em->flush();

        return $status;
    }

    private function dryinjectNotFound()
    {
        return \FOS\RestBundle\View\View::create(['message' => 'Dryinject not found'], Response::HTTP_NOT_FOUND);
    }
}
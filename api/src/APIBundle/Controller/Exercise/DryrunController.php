<?php

namespace APIBundle\Controller\Exercise;

use APIBundle\Controller\InjectTypeController;
use APIBundle\Entity\DryinjectStatus;
use Doctrine\ORM\EntityRepository;
use FOS\RestBundle\View\View;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use APIBundle\Entity\Exercise;
use APIBundle\Form\Type\DryrunType;
use APIBundle\Entity\Dryrun;
use APIBundle\Entity\Event;
use APIBundle\Entity\Incident;
use APIBundle\Entity\Inject;
use APIBundle\Entity\Dryinject;

class DryrunController extends Controller
{
    /**
     * @ApiDoc(
     *    description="List dryruns of an exercise"
     * )
     *
     * @Rest\View(serializerGroups={"dryrun"})
     * @Rest\Get("/exercises/{exercise_id}/dryruns")
     */
    public function getExercisesDryrunsAction(Request $request)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $exercise = $em->getRepository('APIBundle:Exercise')->find($request->get('exercise_id'));
        /* @var $exercise Exercise */

        if (empty($exercise)) {
            return $this->exerciseNotFound();
        }

        $this->denyAccessUnlessGranted('select', $exercise);

        $dryruns = $em->getRepository('APIBundle:Dryrun')->findBy(['dryrun_exercise' => $exercise]);

        foreach ($dryruns as &$dryrun) {
            /** @var Dryrun $dryrun */
            $dryinjects = $em->getRepository('APIBundle:Dryinject')->findBy(['dryinject_dryrun' => $dryrun]);
            $dryrun->computeDryRunFinished($dryinjects);
        }

        return $dryruns;
    }

    /**
     * @ApiDoc(
     *    description="Read a dryrun"
     * )
     *
     * @Rest\View(serializerGroups={"dryrun"})
     * @Rest\Get("/exercises/{exercise_id}/dryruns/{dryrun_id}")
     */
    public function getExerciseDryrunAction(Request $request)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $exercise = $em->getRepository('APIBundle:Exercise')->find($request->get('exercise_id'));
        /* @var $exercise Exercise */

        if (empty($exercise)) {
            return $this->exerciseNotFound();
        }

        $this->denyAccessUnlessGranted('select', $exercise);

        $dryrun = $em->getRepository('APIBundle:Dryrun')->find($request->get('dryrun_id'));
        /* @var $dryrun Dryrun */

        if (empty($dryrun) || $dryrun->getDryrunExercise() !== $exercise) {
            return $this->dryrunNotFound();
        }

        $dryinjects = $em->getRepository('APIBundle:Dryinject')->findBy(['dryinject_dryrun' => $dryrun]);
        $dryrun->computeDryRunFinished($dryinjects);
        return $dryrun;
    }

    /**
     * @ApiDoc(
     *    description="Create a dryrun",
     *    input={"class"=DryrunType::class, "name"=""}
     * )
     *
     * @Rest\View(statusCode=Response::HTTP_CREATED, serializerGroups={"dryrun"})
     * @Rest\Post("/exercises/{exercise_id}/dryruns")
     */
    public function postExercisesDryrunsAction(Request $request)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $exercise = $em->getRepository('APIBundle:Exercise')->find($request->get('exercise_id'));
        /* @var $exercise Exercise */

        if (empty($exercise)) {
            return $this->exerciseNotFound();
        }

        $this->denyAccessUnlessGranted('update', $exercise);

        $dryrun = new Dryrun();
        $form = $this->createForm(DryrunType::class, $dryrun);
        $form->submit($request->request->all());

        if ($form->isValid()) {
            $dryrun->setDryrunExercise($exercise);
            $dryrun->setDryrunDate(new \DateTime());
            $em->persist($dryrun);
            $em->flush();

            // get all injects
            /* @var $events Event[] */
            $events = $em->getRepository('APIBundle:Event')->findBy(['event_exercise' => $exercise]);

            /* @var $injects Inject[] */
            $injects = array();

            //TODO Maybe we can replace this loops by getting all injects with correct join query
            foreach ($events as $event) {
                $incidents = $em->getRepository('APIBundle:Incident')->findBy(['incident_event' => $event]);
                /* @var $incidents Incident[] */

                foreach ($incidents as $incident) {
                    /** @var EntityRepository $injectRepo */
                    $injectRepo = $em->getRepository('APIBundle:Inject');
                    /** Copy only enabled and automatic injects (handler by worker) */
                    $executableInjects = $injectRepo
                        ->createQueryBuilder('i')
                        ->where('i.inject_enabled = true')
                        ->andWhere('i.inject_type != :inject_type_parameter')
                        ->setParameter('inject_type_parameter', InjectTypeController::$INJECT_TYPE_MANUAL)
                        ->andWhere('i.inject_incident = :incident_id_parameter')
                        ->setParameter('incident_id_parameter', $incident)
                        ->getQuery()->getResult();
                    $injects = array_merge($injects, $executableInjects);
                }
            }

            //No need to dryRun an empty exercise
            if (count($injects) == 0) {
                $form->get('dryrun_speed')->addError(new FormError('Please create some injects first'));
                return $form;
            }

            // sort injects executableInjects date
            usort($injects, function ($a, $b) {
                /** @var Inject $a */
                /** @var Inject $b */
                return $a->getInjectDate()->getTimestamp() - $b->getInjectDate()->getTimestamp();
            });

            // create new injects
            /** @var Inject $previousInject */
            $previousInject = null;
            /** @var Dryinject $previousDryInject */
            $previousDryInject = null;

            foreach ($injects as $inject) {
                $dryInject = new Dryinject();
                $dryInject->setDryinjectTitle($inject->getInjectTitle());
                $dryInject->setDryinjectContent($inject->getInjectContent());
                $dryInject->setDryinjectType($inject->getInjectType());
                $dryInject->setDryinjectDryrun($dryrun);

                // set the first inject to now
                if ($previousInject === null) {
                    $dryInject->setDryinjectDate(new \DateTime());
                } else {
                    // compute the interval in seconds from the previous inject
                    $previousDate = $previousInject->getInjectDate()->getTimestamp();
                    $currentDate = $inject->getInjectDate()->getTimestamp();
                    $intervalInSeconds = $currentDate - $previousDate;
                    // accelerate the interval and create the interval object
                    $newInterval = new \DateInterval('PT' . round($intervalInSeconds / $dryrun->getDryrunSpeed()) . 'S');
                    // set the new datetime
                    $dryInject->setDryinjectDate($previousDryInject->getDryinjectDate()->add($newInterval));
                }

                // create the dryInject
                $em->persist($dryInject);
                $em->flush();

                // create the dryInject status
                $status = new DryinjectStatus();
                $status->setStatusDryinject($dryInject);
                $em->persist($status);
                $em->flush();

                $previousInject = $inject;
                $previousDryInject = $dryInject;
            }

            $id = $dryrun->getDryrunId();
            $em->clear();
            $dryrun = $em->getRepository('APIBundle:Dryrun')->find($id);
            return $dryrun;
        } else {
            return $form;
        }
    }

    /**
     * @ApiDoc(
     *    description="Delete a dryrun"
     * )
     *
     * @Rest\View(statusCode=Response::HTTP_NO_CONTENT, serializerGroups={"dryrun"})
     * @Rest\Delete("/exercises/{exercise_id}/dryruns/{dryrun_id}")
     */
    public function removeExercisesDryrunAction(Request $request)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $exercise = $em->getRepository('APIBundle:Exercise')->find($request->get('exercise_id'));
        /* @var $exercise Exercise */

        if (empty($exercise)) {
            return $this->exerciseNotFound();
        }

        $this->denyAccessUnlessGranted('update', $exercise);

        $dryrun = $em->getRepository('APIBundle:Dryrun')->find($request->get('dryrun_id'));
        /* @var $dryrun Dryrun */

        if (empty($dryrun) || $dryrun->getDryrunExercise() !== $exercise) {
            return $this->dryrunNotFound();
        }

        $em->remove($dryrun);
        $em->flush();
    }

    private function exerciseNotFound()
    {
        return View::create(['message' => 'Exercise not found'], Response::HTTP_NOT_FOUND);
    }

    private function dryrunNotFound()
    {
        return View::create(['message' => 'Dryrun not found'], Response::HTTP_NOT_FOUND);
    }
}
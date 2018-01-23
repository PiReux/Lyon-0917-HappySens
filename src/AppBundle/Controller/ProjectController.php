<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Project;
use AppBundle\Service\EmailService;
use AppBundle\Entity\User;
use AppBundle\Service\FileUploader;
use AppBundle\Service\NotificationService;
use AppBundle\Service\SlugService;
use DateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;


/**
 *
 * @Route("project")
 * @Security("user.getIsActive() === true")
 */
class ProjectController extends Controller
{


    /**
     * Creates a new project entity.
     *
     * @Route("/new", name="project_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_EMPLOYE') && user.getIsActive() === true")
     */
    public function newAction(Request $request, FileUploader $fileUploader, SlugService $slugService, EmailService $emailService)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        $nbProjectNotFinish = $em->getRepository('AppBundle:Project')->getProjectStatusNotFinishForOneUser($user->getId());

        if ($nbProjectNotFinish > 0) {
            $this->addFlash(
                'errorNotification',
                'Vous avez déjà un projet en cours !'
            );
            return $this->redirectToRoute('UserProfil', array('slug' => $user->getSlug()));
        }

        $project = new Project();

        $form = $this->createForm('AppBundle\Form\ProjectType', $project);
        $form->remove('author');
        $form->remove('startingDate');
        $form->remove('status');
        $form->remove('likeProjects');
        $form->remove('teamProject');
        $project->setStartingDate(DateTime::createFromFormat ('d/m/Y', date('d/m/Y') ));
        $project->setStatus(1);
        $form->remove('slug');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $project->getPhoto();
            $fileName = $fileUploader->upload($file, "photoProject");
            $project->setPhoto($fileName);
            $project->setSlug($slugService->slugify($project->getTitle(), 'project'));
            $project->setAuthor($user);

            $em = $this->getDoctrine()->getManager();
            $em->persist($project);

            $email_contact = $this->container->getParameter('email_contact');
            $emailService->sendMailProject($project, $email_contact);

            $em->flush();
            $this->addFlash(
                'notif',
                'Votre projet a bien été créé !'
            );
            return $this->redirectToRoute('project_show', array('slug' => $project->getSlug()));
        }

        return $this->render('project/new.html.twig', array(
            'project' => $project,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a project entity.
     *
     * @Route("/{slug}", name="project_show")
     * @Method("GET")
     */
    public function showAction(Project $project)
    {
         $user = $this->getUser();

        $deleteForm = $this->createDeleteForm($project);
        $nbLikes = count($project->getLikeProjects());

        $viewProject = $this->render('project/show.html.twig', array(
            'user' => $user,
            'project' => $project,
            'nbLike' => $nbLikes,
            'delete_form' => $deleteForm->createView(),
        ));

        if($user->getStatus() === User::ROLE_COMPANY or $user->getStatus() === User::ROLE_EMPLOYE) {
            if ($user->getCompany() === $project->getAuthor()->getCompany()) {
                return $viewProject;
            } else {
                throw new AccessDeniedException("Vous n'êtes pas autorisé à voir un projet d'une autre entreprise");
            }
        }

        if($user->getStatus() === User::ROLE_HAPPYCOACH) {
            if ( $project->getHappyCoach() !== NULL) {
                if ($user->getId() === $project->getHappyCoach()->getId()) {
                    return $viewProject;
                }
            }

            foreach ($project->getTeamProject() as $userTeam) {
                if ($userTeam->getId() === $user->getId()) {
                    return $viewProject;
                }
            }
            throw new AccessDeniedException("Vous n'êtes pas autorisé à travailler sur ce projet");
        }
        return $viewProject;
    }

    /**
     * Displays a form to edit an existing project entity.
     *
     * @Route("/{slug}/edit", name="project_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Project $project, FileUploader $fileUploader, SlugService $slugService)
    {
        if ($project->getPhoto()) {
            $photoTemp = $project->getPhoto();
            $project->setPhoto(
                new File($this->getParameter('upload_directory').'/photoProject/'.$project->getPhoto())
            );
        } else {
            $photoTemp = $project->getPhoto();
        }

        $editForm = $this->createForm('AppBundle\Form\ProjectType', $project);
        $editForm->remove('startingDate');
        $editForm->remove('author');
        $editForm->remove('status');
        $editForm->remove('likeProjects');
        $editForm->remove('teamProject');
        $editForm->remove('slug');
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            if ($editForm->getData()->getPhoto() !== NULL) {
                $file = $project->getPhoto();
                $fileName = $fileUploader->upload($file, "photoProject");
                $project->setPhoto($fileName);
            } else {
                $project->setPhoto($photoTemp);
            }

            $project->setSlug($slugService->slugify($project->getTitle(), 'project'));
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('project_show', array('slug' => $project->getSlug()));
        }

        return $this->render('project/edit.html.twig', array(
            'project' => $project,
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Deletes a project entity.
     *
     * @Route("/{slug}", name="project_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Project $project)
    {
        $form = $this->createDeleteForm($project);
        $form->remove('author');
        $form->remove('status');
        $form->remove('photo');
        $form->remove('likeProjects');
        $form->remove('teamProject');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($project);
            $em->flush();
        }

        return $this->redirectToRoute('project_index');
    }

    /**
     * Creates a form to delete a project entity.
     *
     * @param Project $project The project entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Project $project)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('project_delete', array('slug' => $project->getSlug())))
            ->setMethod('DELETE')
            ->getForm();

    }

    /**
     * @Route("/joinTeam/{slug}/", name="join_team")
     *
     */
    // TODO: AJAX
    public function joinAction(Request $request, Project $project)
    {
        $idU = $this->getUser();
        $add = $project->addTeamProject($idU);
        $em = $this->getDoctrine()->getManager();
        $em->persist($add);
        $em->flush();
        return $this->redirectToRoute('project_show', array('slug' => $project->getSlug()));
    }

    /**
     * @Route("/quitTeam/{slug}/", name="quit_team")
     *
     */
    // TODO: AJAX
    public function quitAction(Request $request, Project $project)
    {
        $idU = $this->getUser();
        $rm = $project->removeTeamProject($idU);
        $em = $this->getDoctrine()->getManager();
        $em->persist($rm);
        $em->flush();
        return $this->redirectToRoute('project_show', array('slug' => $project->getSlug()));
    }


    /**
     *
     * @Route("/likeProject/", name="like_project")
     * @Method("POST")
     *
     * @param Request $request
     * @param NotificationService $notificationService
     * @return $this|JsonResponse
     */
    public function likeAction(Request $request, NotificationService $notificationService)
    {
        if($request->isXmlHttpRequest()) {
            $user = $this->getUser();
            $em = $this->getDoctrine()->getManager();
            $id = $request->request->get('id');
            $project = $em->getRepository('AppBundle:Project')->find($id);
            if ($user->getId() !== $project->getAuthor()->getId()) {
                $add = $project->addLikeProject($user);
                $em->persist($add);
                $em->flush();
                $notificationService->sendNotif($user, $project->getAuthor()->getId());
                $allLikes = [$project->getLikeProjects()];
                return new JsonResponse(json_encode(['likeSend' => count($allLikes)]));
            } else {
                return new JsonResponse(['badUser' => json_encode('Vous ne pouvez pas aimer votre propre projet')]);
            }
        }
        return new JsonResponse(json_encode(['likeFailed' => "Erreur lors de l'envoi du like"]));
    }

    /**
     *
     * @Route("/dislikeProject/", name="dislike_project")
     * @Method("POST")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dislikeAction(Request $request)
    {
        if($request->isXmlHttpRequest()) {
            $user = $this->getUser();
            $em = $this->getDoctrine()->getManager();
            $id = $request->request->get('id');
            $project = $em->getRepository('AppBundle:Project')->find($id);
            $project->removeLikeProject($user);
            $em->flush();
            return new JsonResponse(['data' => json_encode('Vous n\'aimez plus le projet')]);
        }
        return new JsonResponse(['data' => json_encode('Failed')]);
    }

    /**
     * @Route("/validProject/{slug}/", name="project_validate")
     *
     */
    // TODO: AJAX
    public function validateProjectAction(Request $request, Project $project, EmailService $emailService)
    {
        $statusProject = $project->setStatus('2');
        $em = $this->getDoctrine()->getManager();
        $em->persist($statusProject);

        $email_contact = $this->container->getParameter('email_contact');
        $emailService->sendMailProjectValidate($project, $email_contact);

        $em->flush();
        return $this->redirectToRoute('project_show', array('slug' => $project->getSlug()));
    }

    /**
     * @Route("/finishProject/{slug}/", name="project_finish")
     *
     */
    // TODO: AJAX
    public function finishProjectAction(Request $request, Project $project)
    {
        $today = new \DateTime();
        $project->setStatus('3');
        $project->setEndDate($today);
        $em = $this->getDoctrine()->getManager();
        $em->persist($project);
        $em->flush();
        return $this->redirectToRoute('project_show', array('slug' => $project->getSlug()));
    }

    /**
     * @Route("/reopenProject/{slug}/", name="project_reopen")
     *
     */
    // TODO: AJAX
    public function reopenProjectAction(Request $request, Project $project)
    {
        $newDateEnd = new \DateTime();
        $newDateEnd->add(new \DateInterval('P1M'));
        $project->setStatus('2');
        $project->setEndDate($newDateEnd);
        $em = $this->getDoctrine()->getManager();
        $em->persist($project);
        $em->flush();
        return $this->redirectToRoute('project_show', array('slug' => $project->getSlug()));
    }
}

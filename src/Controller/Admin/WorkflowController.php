<?php

namespace App\Controller\Admin;

use App\Entity\Workflow;
use App\Form\WorkflowType;
use App\Repository\WorkflowRepository;
use App\Repository\ProcedureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/workflow')]
class WorkflowController extends AbstractController
{
    #[Route('/', name: 'admin_workflow_index', methods: ['GET'])]
    public function index(WorkflowRepository $workflowRepository): Response
    {
        $workflows = $workflowRepository->createQueryBuilder('w')
            ->leftJoin('w.procedure', 'p')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.providingAdministration', 'pa')
            ->addSelect('p', 'f', 'pa')
            ->orderBy('p.pname', 'ASC')
            ->addOrderBy('w.stepOrder', 'ASC')
            ->addOrderBy('w.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
        
        return $this->render('admin/workflow/index.html.twig', [
            'workflows' => $workflows,
        ]);
    }

    #[Route('/new', name: 'admin_workflow_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, WorkflowRepository $workflowRepository): Response
    {
        $workflow = new Workflow();
        
        // Set default step order if procedure is pre-selected
        $procedureId = $request->query->get('procedure');
        if ($procedureId) {
            $procedure = $entityManager->getRepository('App\Entity\Procedure')->find($procedureId);
            if ($procedure) {
                $workflow->setProcedure($procedure);
                $nextOrder = $workflowRepository->getNextStepOrderForProcedure($procedure);
                $workflow->setStepOrder($nextOrder);
            }
        }
        
        $form = $this->createForm(WorkflowType::class, $workflow);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Ensure step order is set correctly
                if (!$workflow->getStepOrder()) {
                    $nextOrder = $workflowRepository->getNextStepOrderForProcedure($workflow->getProcedure());
                    $workflow->setStepOrder($nextOrder);
                }

                $entityManager->persist($workflow);
                $entityManager->flush();

                $this->addFlash('success', 'Workflow step has been created successfully.');
                
                // Redirect back to procedure if came from procedure page
                if ($procedureId) {
                    return $this->redirectToRoute('admin_procedure_show', ['id' => $procedureId], Response::HTTP_SEE_OTHER);
                }
                
                return $this->redirectToRoute('admin_workflow_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating workflow step: ' . $e->getMessage());
            }
        }

        return $this->render('admin/workflow/new.html.twig', [
            'workflow' => $workflow,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_workflow_show', methods: ['GET'])]
    public function show(Workflow $workflow): Response
    {
        return $this->render('admin/workflow/show.html.twig', [
            'workflow' => $workflow,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_workflow_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Workflow $workflow, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(WorkflowType::class, $workflow);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();

                $this->addFlash('success', 'Workflow step has been updated successfully.');
                return $this->redirectToRoute('admin_workflow_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating workflow step: ' . $e->getMessage());
            }
        }

        return $this->render('admin/workflow/edit.html.twig', [
            'workflow' => $workflow,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_workflow_delete', methods: ['POST'])]
    public function delete(Request $request, Workflow $workflow, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$workflow->getId(), $request->request->get('_token'))) {
            try {
                // Soft delete by setting isActive to false
                $workflow->setIsActive(false);
                $entityManager->flush();

                $this->addFlash('success', 'Workflow step has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting workflow step: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_workflow_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'admin_workflow_toggle_status', methods: ['POST'])]
    public function toggleStatus(Workflow $workflow, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $workflow->setIsActive(!$workflow->isActive());
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $workflow->isActive(),
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/toggle-required', name: 'admin_workflow_toggle_required', methods: ['POST'])]
    public function toggleRequired(Workflow $workflow, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $workflow->setIsRequired(!$workflow->isRequired());
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'required' => $workflow->isRequired(),
                'message' => 'Required status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating required status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/by-procedure/{id}', name: 'admin_workflow_by_procedure', methods: ['GET'])]
    public function byProcedure(int $id, WorkflowRepository $workflowRepository, ProcedureRepository $procedureRepository): Response
    {
        $procedure = $procedureRepository->find($id);
        
        if (!$procedure) {
            throw $this->createNotFoundException('Procedure not found');
        }

        $workflows = $workflowRepository->findByProcedure($procedure);

        return $this->render('admin/workflow/by_procedure.html.twig', [
            'procedure' => $procedure,
            'workflows' => $workflows,
        ]);
    }

    #[Route('/reorder/{procedureId}', name: 'admin_workflow_reorder', methods: ['POST'])]
    public function reorderSteps(int $procedureId, Request $request, EntityManagerInterface $entityManager, ProcedureRepository $procedureRepository): JsonResponse
    {
        try {
            $procedure = $procedureRepository->find($procedureId);
            if (!$procedure) {
                return new JsonResponse(['success' => false, 'message' => 'Procedure not found'], 404);
            }

            $workflowIds = $request->request->get('workflow_ids', []);
            
            foreach ($workflowIds as $index => $workflowId) {
                $workflow = $entityManager->getRepository(Workflow::class)->find($workflowId);
                if ($workflow && $workflow->getProcedure() === $procedure) {
                    $workflow->setStepOrder($index + 1);
                }
            }

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Workflow steps have been reordered successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error reordering steps: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/duplicate/{id}', name: 'admin_workflow_duplicate', methods: ['POST'])]
    public function duplicate(Workflow $workflow, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $newWorkflow = new Workflow();
            $newWorkflow->setName($workflow->getName() . ' (Copy)');
            $newWorkflow->setShortDescription($workflow->getShortDescription());
            $newWorkflow->setInputs($workflow->getInputs());
            $newWorkflow->setOutput($workflow->getOutput());
            $newWorkflow->setProcedure($workflow->getProcedure());
            $newWorkflow->setStepOrder($workflow->getStepOrder() + 1);
            $newWorkflow->setDisplayOrder($workflow->getDisplayOrder());
            $newWorkflow->setIsRequired($workflow->isRequired());

            // Update step orders for subsequent workflows
            $subsequentWorkflows = $entityManager->getRepository(Workflow::class)
                ->createQueryBuilder('w')
                ->where('w.procedure = :procedure')
                ->andWhere('w.stepOrder > :order')
                ->setParameter('procedure', $workflow->getProcedure())
                ->setParameter('order', $workflow->getStepOrder())
                ->getQuery()
                ->getResult();

            foreach ($subsequentWorkflows as $subsequentWorkflow) {
                $subsequentWorkflow->setStepOrder($subsequentWorkflow->getStepOrder() + 1);
            }

            $entityManager->persist($newWorkflow);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Workflow step has been duplicated successfully.',
                'new_id' => $newWorkflow->getId()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error duplicating workflow step: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/filter', name: 'admin_workflow_filter', methods: ['GET'])]
    public function filter(Request $request, WorkflowRepository $workflowRepository): JsonResponse
    {
        $procedure = $request->query->get('procedure');
        $inputType = $request->query->get('input_type');
        $outputType = $request->query->get('output_type');
        $search = $request->query->get('search');

        $qb = $workflowRepository->createQueryBuilder('w')
            ->leftJoin('w.procedure', 'p')
            ->addSelect('p')
            ->where('w.isActive = :active')
            ->setParameter('active', true);

        if ($procedure) {
            $qb->andWhere('w.procedure = :procedure')
               ->setParameter('procedure', $procedure);
        }

        if ($inputType) {
            $qb->andWhere('JSON_CONTAINS(w.inputs, :inputType) = 1')
               ->setParameter('inputType', json_encode($inputType));
        }

        if ($outputType) {
            $qb->andWhere('w.output = :outputType')
               ->setParameter('outputType', $outputType);
        }

        if ($search) {
            $qb->andWhere('w.name LIKE :search OR w.shortDescription LIKE :search OR p.pname LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $workflows = $qb->orderBy('p.pname', 'ASC')
                        ->addOrderBy('w.stepOrder', 'ASC')
                        ->getQuery()
                        ->getResult();

        $data = [];
        foreach ($workflows as $workflow) {
            $data[] = [
                'id' => $workflow->getId(),
                'name' => $workflow->getName(),
                'procedure' => $workflow->getProcedure()->getPname(),
                'stepOrder' => $workflow->getStepOrder(),
                'inputs' => $workflow->getInputsLabels(),
                'output' => $workflow->getOutputLabel(),
                'isRequired' => $workflow->isRequired(),
                'isActive' => $workflow->isActive()
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/statistics', name: 'admin_workflow_statistics', methods: ['GET'])]
    public function statistics(WorkflowRepository $workflowRepository): JsonResponse
    {
        $stats = $workflowRepository->getWorkflowStats();
        $mostUsedInputs = $workflowRepository->getMostUsedInputs();
        $mostUsedOutputs = $workflowRepository->getMostUsedOutputs();

        return new JsonResponse([
            'general_stats' => $stats,
            'most_used_inputs' => $mostUsedInputs,
            'most_used_outputs' => $mostUsedOutputs
        ]);
    }

    #[Route('/export/{procedureId}', name: 'admin_workflow_export', methods: ['GET'])]
    public function exportProcedureWorkflows(int $procedureId, WorkflowRepository $workflowRepository, ProcedureRepository $procedureRepository): JsonResponse
    {
        $procedure = $procedureRepository->find($procedureId);
        if (!$procedure) {
            return new JsonResponse(['error' => 'Procedure not found'], 404);
        }

        $workflows = $workflowRepository->findByProcedure($procedure);
        
        $data = [
            'procedure' => [
                'id' => $procedure->getId(),
                'name' => $procedure->getPname(),
                'description' => $procedure->getShortDesc()
            ],
            'workflows' => []
        ];

        foreach ($workflows as $workflow) {
            $data['workflows'][] = [
                'step_order' => $workflow->getStepOrder(),
                'name' => $workflow->getName(),
                'description' => $workflow->getShortDescription(),
                'inputs' => $workflow->getInputs(),
                'output' => $workflow->getOutput(),
                'is_required' => $workflow->isRequired()
            ];
        }

        return new JsonResponse($data);
    }
}
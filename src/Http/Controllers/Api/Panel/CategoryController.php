<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Services\CategoryTreeService;

class CategoryController extends BaseController
{
    public function __construct(protected CategoryTreeService $treeService)
    {
    }

    /**
     * @OA\Get(
     *     path="/categories/tree",
     *     tags={"Catalog"},
     *     summary="Get category tree",
     *     @OA\Parameter(
     *         name="include_inactive",
     *         in="query",
     *         description="Set true to include inactive categories",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category tree",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/CategoryTreeNode")
     *             )
     *         )
     *     )
     * )
     */
    public function tree(Request $request)
    {
        $activeOnly = !$request->boolean('include_inactive');
        $rawTree = $this->treeService->getTree($activeOnly);
        $simplified = $this->mapTree($rawTree);

        return $this->apiSuccess($simplified);
    }

    protected function mapTree(array $nodes): array
    {
        return array_map(function (array $node) {
            $data = $node['data'] ?? [];

            return [
                'id' => (int) ($data['id'] ?? $node['key'] ?? 0),
                'name' => (string) ($node['title'] ?? ''),
                'slug' => (string) ($data['slug'] ?? ''),
                'active' => (bool) ($data['active'] ?? true),
                'sort' => (int) ($data['sort'] ?? 0),
                'children' => isset($node['children'])
                    ? $this->mapTree($node['children'])
                    : [],
            ];
        }, $nodes);
    }
}


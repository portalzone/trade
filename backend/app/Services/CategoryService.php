<?php

namespace App\Services;

use App\Models\Storefront;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    /**
     * Create a new category
     */
    public function createCategory(Storefront $storefront, array $data): ProductCategory
    {
        DB::beginTransaction();

        try {
            // Validate parent category belongs to same storefront
            if (isset($data['parent_id'])) {
                $parent = ProductCategory::find($data['parent_id']);
                if ($parent && $parent->storefront_id !== $storefront->id) {
                    throw new \Exception('Parent category must belong to the same storefront');
                }
            }

            $category = ProductCategory::create([
                'storefront_id' => $storefront->id,
                'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            AuditService::log(
                'category.created',
                "Category created: {$category->name}",
                $category,
                [],
                ['category_name' => $category->name],
                ['user_id' => $storefront->user_id, 'storefront_id' => $storefront->id]
            );

            DB::commit();

            return $category->fresh(['parent', 'children']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update category
     */
    public function updateCategory(ProductCategory $category, array $data): ProductCategory
    {
        DB::beginTransaction();

        try {
            $oldData = $category->toArray();

            // Prevent circular parent reference
            if (isset($data['parent_id']) && $data['parent_id']) {
                if ($data['parent_id'] == $category->id) {
                    throw new \Exception('Category cannot be its own parent');
                }

                // Check if new parent is a descendant
                if ($this->isDescendant($category->id, $data['parent_id'])) {
                    throw new \Exception('Cannot set a descendant as parent');
                }
            }

            $category->update(array_filter($data, function ($value) {
                return $value !== null;
            }));

            AuditService::log(
                'category.updated',
                "Category updated: {$category->name}",
                $category,
                $oldData,
                [],
                ['user_id' => $category->storefront->user_id, 'storefront_id' => $category->storefront_id]
            );

            DB::commit();

            return $category->fresh(['parent', 'children']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete category
     */
    public function deleteCategory(ProductCategory $category): bool
    {
        DB::beginTransaction();

        try {
            // Check if category has products
            if ($category->products()->count() > 0) {
                throw new \Exception('Cannot delete category with products. Please reassign or delete products first.');
            }

            // Check if category has children
            if ($category->hasChildren()) {
                throw new \Exception('Cannot delete category with subcategories. Please delete subcategories first.');
            }

            $categoryName = $category->name;
            $storefrontId = $category->storefront_id;
            $userId = $category->storefront->user_id;

            $category->delete();

            AuditService::log(
                'category.deleted',
                "Category deleted: {$categoryName}",
                null,
                $category->toArray(),
                [],
                ['user_id' => $userId, 'storefront_id' => $storefrontId]
            );

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Build hierarchical category tree
     */
    public function buildCategoryTree($categories, $parentId = null)
    {
        $branch = [];

        foreach ($categories as $category) {
            if ($category->parent_id == $parentId) {
                $children = $this->buildCategoryTree($categories, $category->id);
                
                $categoryArray = $category->toArray();
                
                if ($children) {
                    $categoryArray['children'] = $children;
                }
                
                $branch[] = $categoryArray;
            }
        }

        return $branch;
    }

    /**
     * Check if a category is a descendant of another
     */
    protected function isDescendant(int $categoryId, int $potentialDescendantId): bool
    {
        $category = ProductCategory::find($potentialDescendantId);
        
        while ($category) {
            if ($category->id === $categoryId) {
                return true;
            }
            $category = $category->parent;
        }

        return false;
    }

    /**
     * Get category path (breadcrumb)
     */
    public function getCategoryPath(ProductCategory $category): array
    {
        $path = [];
        $current = $category;

        while ($current) {
            array_unshift($path, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }

        return $path;
    }

    /**
     * Reorder categories
     */
    public function reorderCategories(array $categoryOrder): void
    {
        DB::beginTransaction();

        try {
            foreach ($categoryOrder as $order => $categoryId) {
                ProductCategory::where('id', $categoryId)
                    ->update(['sort_order' => $order]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get category statistics
     */
    public function getCategoryStats(ProductCategory $category): array
    {
        return [
            'total_products' => $category->products()->count(),
            'active_products' => $category->products()->where('is_active', true)->count(),
            'total_subcategories' => $category->children()->count(),
            'has_children' => $category->hasChildren(),
            'depth_level' => $this->getCategoryDepth($category),
        ];
    }

    /**
     * Get category depth level
     */
    protected function getCategoryDepth(ProductCategory $category): int
    {
        $depth = 0;
        $current = $category;

        while ($current->parent) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }
}

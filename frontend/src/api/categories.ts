import apiClient from './apiClient'

// ============== Types ==============

export interface Category {
  id: string
  name: string
  description: string | null
  parent_id: string | null
  department_id?: string
  sort_order: number
  material_count: number
}

export interface CreateCategoryRequest {
  department_id: string
  name: string
  description?: string | null
  parent_id?: string | null
  sort_order?: number
}

export interface UpdateCategoryRequest {
  name?: string
  description?: string | null
  parent_id?: string | null
  sort_order?: number
}

// ============== API Functions ==============

/**
 * Lädt alle Kategorien für ein Department
 */
export async function getCategories(departmentId: string): Promise<Category[]> {
  const response = await apiClient.get<Category[]>(`/api/categories?department_id=${departmentId}`)
  return response.data
}

/**
 * Lädt eine einzelne Kategorie
 */
export async function getCategory(id: string): Promise<Category> {
  const response = await apiClient.get<Category>(`/api/categories/${id}`)
  return response.data
}

/**
 * Erstellt eine neue Kategorie
 */
export async function createCategory(data: CreateCategoryRequest): Promise<Category> {
  const response = await apiClient.post<Category>('/api/categories', data)
  return response.data
}

/**
 * Aktualisiert eine Kategorie
 */
export async function updateCategory(id: string, data: UpdateCategoryRequest): Promise<Category> {
  const response = await apiClient.patch<Category>(`/api/categories/${id}`, data)
  return response.data
}

/**
 * Löscht eine Kategorie
 */
export async function deleteCategory(id: string): Promise<void> {
  await apiClient.delete(`/api/categories/${id}`)
}

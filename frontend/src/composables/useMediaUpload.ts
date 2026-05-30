import { ref } from 'vue'
import {
  extractMediaUploadError,
  uploadMediaFile,
  validateImageFile,
} from '@/api/media'

export { extractMediaUploadError, uploadMediaFile, validateImageFile }

export function useMediaUpload() {
  const uploading = ref(false)

  async function upload(uploadUrl: string, file: File, fieldName = 'photo'): Promise<unknown> {
    uploading.value = true
    try {
      const { data } = await uploadMediaFile(uploadUrl, file, { fieldName })
      return data
    } finally {
      uploading.value = false
    }
  }

  return {
    uploading,
    upload,
    uploadMediaFile,
    validateImageFile,
    extractMediaUploadError,
  }
}

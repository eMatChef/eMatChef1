import { usePromptStore, type PromptOptions } from '@/stores/prompt'

export function usePrompt() {
  const promptStore = usePromptStore()

  return {
    prompt: (options: PromptOptions): Promise<string | null> =>
      promptStore.show(options),
  }
}

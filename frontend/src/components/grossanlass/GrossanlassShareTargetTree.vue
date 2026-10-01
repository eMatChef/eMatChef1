<template>
  <v-expansion-panels
    v-if="branchNodes.length"
    v-model="openIds"
    multiple
    class="e-accordions share-target-tree"
    :class="{ 'e-accordions--nested': nested }"
  >
    <v-expansion-panel v-for="node in branchNodes" :key="node.id" :value="node.id">
      <v-expansion-panel-title>
        <span class="share-target-tree__row">
          <button
            type="button"
            class="share-target-tree__pick"
            :class="{ 'share-target-tree__pick--on': model === node.id }"
            :disabled="!node.selectable"
            :aria-pressed="model === node.id"
            @click.stop="select(node)"
          >
            <span class="share-target-tree__radio" aria-hidden="true" />
          </button>
          <GrossanlassGroupNodeIcon :node-type="node.nodeType" />
          <strong class="share-target-tree__name" @click="onNameClick(node, $event)">{{ node.name }}</strong>
          <button
            v-if="node.canCreate"
            type="button"
            class="share-target-tree__add"
            :title="t('grossanlass.planung.ressorts.shareCreate')"
            :disabled="createSaving"
            @click.stop="toggleCreate(node.id)"
          >
            <v-icon icon="mdi-plus" size="18" />
          </button>
          <span class="share-target-tree__count">{{ node.children.length }}</span>
        </span>
      </v-expansion-panel-title>
      <v-expansion-panel-text>
        <ShareCreateRow
          v-if="creatingParentId === node.id"
          v-model="createName"
          :saving="createSaving"
          @submit="emit('create')"
          @cancel="creatingParentId = null"
        />
        <GrossanlassShareTargetTree
          v-model="model"
          v-model:creating-parent-id="creatingParentId"
          v-model:create-name="createName"
          nested
          :nodes="node.children"
          :create-saving="createSaving"
          @create="emit('create')"
        />
      </v-expansion-panel-text>
    </v-expansion-panel>
  </v-expansion-panels>

  <ul v-if="leafNodes.length" class="share-target-tree__leaves" role="list">
    <li v-for="node in leafNodes" :key="node.id">
      <div class="share-target-tree__leaf-row">
        <button
          type="button"
          class="share-target-tree__leaf"
          :class="{ 'share-target-tree__leaf--on': model === node.id }"
          :disabled="!node.selectable"
          :aria-pressed="model === node.id"
          @click="select(node)"
        >
          <span class="share-target-tree__radio" aria-hidden="true" />
          <GrossanlassGroupNodeIcon :node-type="node.nodeType" />
          <strong class="share-target-tree__name">{{ node.name }}</strong>
        </button>
        <button
          v-if="node.canCreate"
          type="button"
          class="share-target-tree__add"
          :title="t('grossanlass.planung.ressorts.shareCreate')"
          :disabled="createSaving"
          @click="toggleCreate(node.id)"
        >
          <v-icon icon="mdi-plus" size="18" />
        </button>
      </div>
      <ShareCreateRow
        v-if="creatingParentId === node.id"
        v-model="createName"
        :saving="createSaving"
        @submit="emit('create')"
        @cancel="creatingParentId = null"
      />
    </li>
  </ul>

  <div v-if="!nested && canCreateRoot" class="share-target-tree__root">
    <button
      type="button"
      class="share-target-tree__add share-target-tree__add--row"
      :disabled="createSaving"
      @click="toggleCreate('')"
    >
      <v-icon icon="mdi-plus" size="18" />
      {{ t('grossanlass.planung.ressorts.shareCreateRoot') }}
    </button>
    <ShareCreateRow
      v-if="creatingParentId === ''"
      v-model="createName"
      :saving="createSaving"
      @submit="emit('create')"
      @cancel="creatingParentId = null"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ShareCreateRow from '@/components/grossanlass/GrossanlassShareCreateRow.vue'
import GrossanlassGroupNodeIcon from '@/components/grossanlass/GrossanlassGroupNodeIcon.vue'

export type ShareTargetNode = {
  id: string
  name: string
  kind: string
  nodeType: 'ressort' | 'unterressort' | 'bauprojekt'
  selectable: boolean
  canCreate: boolean
  children: ShareTargetNode[]
}

defineOptions({ name: 'GrossanlassShareTargetTree' })

const props = withDefaults(defineProps<{
  nodes: ShareTargetNode[]
  nested?: boolean
  createSaving?: boolean
  canCreateRoot?: boolean
}>(), {
  nested: false,
  createSaving: false,
  canCreateRoot: false,
})

const emit = defineEmits<{
  create: []
}>()

const { t } = useI18n()
const model = defineModel<string>({ default: '' })
const creatingParentId = defineModel<string | null>('creatingParentId', { default: null })
const createName = defineModel<string>('createName', { default: '' })
const openIds = ref<string[]>([])

const branchNodes = computed(() => props.nodes.filter((node) => node.children.length > 0))
const leafNodes = computed(() => props.nodes.filter((node) => node.children.length === 0))

watch(
  () => props.nodes.map((node) => node.id).join(','),
  () => {
    const ids = branchNodes.value.map((node) => node.id)
    const kept = openIds.value.filter((id) => ids.includes(id))
    openIds.value = kept.length > 0 ? kept : ids
  },
  { immediate: true },
)

function select(node: ShareTargetNode) {
  if (!node.selectable) return
  model.value = node.id
}

function onNameClick(node: ShareTargetNode, event: MouseEvent) {
  if (!node.selectable) return
  event.stopPropagation()
  select(node)
}

function toggleCreate(parentId: string) {
  if (creatingParentId.value === parentId) {
    creatingParentId.value = null
    createName.value = ''
    return
  }
  creatingParentId.value = parentId
  createName.value = ''
  if (!openIds.value.includes(parentId)) {
    openIds.value = [...openIds.value, parentId]
  }
}
</script>

<style scoped>
.share-target-tree__row,
.share-target-tree__leaf,
.share-target-tree__leaf-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  width: 100%;
}

.share-target-tree__pick,
.share-target-tree__leaf,
.share-target-tree__add {
  appearance: none;
  border: 0;
  background: transparent;
  padding: 0;
  font: inherit;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.share-target-tree__pick:disabled,
.share-target-tree__leaf:disabled,
.share-target-tree__add:disabled {
  cursor: default;
  opacity: 0.55;
}

.share-target-tree__leaf-row {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  min-height: 56px;
  padding: 10px 14px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.share-target-tree__leaf {
  flex: 1;
  padding: 4px 0;
  border-radius: 8px;
}

.share-target-tree__leaf:hover:not(:disabled),
.share-target-tree__pick:hover:not(:disabled) {
  background: #f8fafc;
}

.share-target-tree__leaf--on,
.share-target-tree__pick--on {
  background: #ecfdf5;
}

.share-target-tree__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.share-target-tree__count {
  font-size: 0.75rem;
  color: #64748b;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 1px 8px;
}

.share-target-tree__add {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  color: #0f766e;
}

.share-target-tree__add:hover:not(:disabled) {
  background: #ccfbf1;
}

.share-target-tree__radio {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  border: 2px solid #94a3b8;
  border-radius: 50%;
  background: #fff;
}

.share-target-tree__pick--on .share-target-tree__radio,
.share-target-tree__leaf--on .share-target-tree__radio {
  border-color: #0f766e;
  box-shadow: inset 0 0 0 3px #0f766e;
}

.share-target-tree__pick:disabled .share-target-tree__radio,
.share-target-tree__leaf:disabled .share-target-tree__radio {
  visibility: hidden;
}

.share-target-tree__leaves {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.share-target-tree.e-accordions--nested + .share-target-tree__leaves,
.share-target-tree + .share-target-tree__leaves {
  margin-top: 8px;
}

.share-target-tree__root {
  margin-top: 10px;
}

.share-target-tree__add--row {
  width: 100%;
  justify-content: flex-start;
  gap: 6px;
  height: auto;
  padding: 8px 10px;
  border: 1px dashed #99f6e4;
  border-radius: 12px;
  font-size: 0.88rem;
  font-weight: 600;
}
</style>

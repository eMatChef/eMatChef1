<template>
  <div class="grossanlass-ressorts">
    <div v-if="!isLoading && groups.length > 0" class="stats-bar">
      <div class="stat-item">
        <span class="stat-value">{{ rootCount }}</span>
        <span class="stat-label">{{ t('grossanlass.planung.ressorts.statRessorts') }}</span>
      </div>
      <div class="stat-item">
        <span class="stat-value">{{ groups.length }}</span>
        <span class="stat-label">{{ t('grossanlass.planung.ressorts.statNodes') }}</span>
      </div>
      <div class="stat-item">
        <span class="stat-value">{{ totalMembers }}</span>
        <span class="stat-label">{{ t('grossanlass.planung.ressorts.statMembers') }}</span>
      </div>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="list"
      :message="t('grossanlass.planung.ressorts.loading')"
    />

    <div v-else-if="error" class="ressorts-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadGroups">{{ t('common.retry') }}</EButton>
    </div>

    <EEmptyState
      v-else-if="groups.length === 0"
      variant="create"
      icon="mdi-sitemap"
      :title="t('grossanlass.planung.ressorts.emptyTitle')"
      :description="t('grossanlass.planung.ressorts.emptyDescription')"
    >
      <template v-if="canCreateRoot()" #actions>
        <EButton @click="openCreateModal()">{{ t('grossanlass.planung.ressorts.addAction') }}</EButton>
      </template>
    </EEmptyState>

    <template v-else>
    <div class="ressorts-toolbar">
      <v-tabs
        v-model="activeSubTab"
        class="materials-view-tabs ressorts-subtabs"
        color="primary"
        show-arrows
      >
        <v-tab value="ressorts">
          {{ t('grossanlass.planung.ressorts.panelRessorts') }}
          <span class="materials-view-tab-count">{{ groups.length }}</span>
        </v-tab>
        <v-tab value="members">
          {{ t('grossanlass.planung.ressorts.panelMembers') }}
          <span class="materials-view-tab-count">{{ uniqueMembers.length }}</span>
        </v-tab>
      </v-tabs>
      <EButton v-if="activeSubTab === 'ressorts' && canCreateRoot()" variant="primary" @click="openCreateModal()">
        <v-icon icon="mdi-plus" start size="20" />
        {{ t('grossanlass.planung.ressorts.addAction') }}
      </EButton>
      <EButton
        v-else-if="activeSubTab === 'members' && canAddOverviewMembers"
        variant="primary"
        @click="openAddMemberDialog"
      >
        <v-icon icon="mdi-plus" start size="20" />
        {{ t('grossanlass.planung.ressorts.addMembersAction') }}
      </EButton>
    </div>

    <div v-if="activeSubTab === 'ressorts'" class="ressorts-subtab">
    <p v-if="canFullyManage && !logisticsGroupId" class="cost-hint">
      {{ t('grossanlass.planung.ressorts.costSetHint') }}
    </p>
    <div class="table-wrapper">
      <table class="groups-table">
        <thead>
          <tr>
            <th class="col-name">{{ t('grossanlass.planung.ressorts.colName') }}</th>
            <th class="col-members">{{ t('grossanlass.planung.ressorts.colMembers') }}</th>
            <th v-if="showManagementActions" class="col-actions"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="group in hierarchicalGroups"
            :key="group.id"
            class="group-row"
            :class="{ 'is-child': group._level > 0 }"
          >
            <td class="col-name">
              <div class="name-cell" :style="{ paddingLeft: group._level * 24 + 'px' }">
                <span v-if="group._level > 0" class="indent-icon">↳</span>
                <GrossanlassGroupNodeIcon :node-type="group.node_type" />
                <div class="name-stack">
                  <span class="group-name">{{ group.name }}</span>
                  <span v-if="group.node_type === 'bauprojekt' && projectWindow(group)" class="window-chip">
                    {{ projectWindow(group) }}
                  </span>
                  <span class="kind-row">
                    <span class="kind-badge">{{ kindLabel(group) }}</span>
                    <button
                      v-if="isLogisticsNode(group) && canFullyManage"
                      type="button"
                      class="cost-flag is-editable"
                      :title="t('grossanlass.planung.ressorts.costFlagHint')"
                      :disabled="isSavingLogistics"
                      @click="clearLogisticsNode"
                    >
                      {{ t('grossanlass.planung.ressorts.costFlag') }}
                    </button>
                    <span
                      v-else-if="isLogisticsNode(group)"
                      class="cost-flag"
                    >
                      {{ t('grossanlass.planung.ressorts.costFlag') }}
                    </span>
                    <button
                      v-else-if="canSetLogisticsNode(group)"
                      type="button"
                      class="cost-set-btn"
                      :disabled="isSavingLogistics"
                      :title="t('grossanlass.planung.ressorts.costSet')"
                      @click="setLogisticsNode(group)"
                    >
                      {{ t('grossanlass.planung.ressorts.costSet') }}
                    </button>
                  </span>
                </div>
              </div>
            </td>
            <td class="col-members">
              <div class="user-avatar-badge-list">
                <template v-if="getGroupMembersForDisplay(group).length > 0">
                  <UserAvatarBadge
                    v-for="member in getGroupMembersForDisplay(group)"
                    :key="member.user_id"
                    :user="member"
                    :dept-stage-role="deptRoleForUser(member.user_id)"
                  />
                </template>
                <span v-else class="text-muted">–</span>
              </div>
            </td>
            <td v-if="showManagementActions" class="col-actions">
              <div class="action-buttons">
                <button
                  v-if="group.node_type === 'bauprojekt'"
                  class="action-btn"
                  :title="t('grossanlass.planung.ressorts.openProject')"
                  @click="openProjectPanel(group)"
                >
                  <v-icon icon="mdi-clipboard-list-outline" size="16" />
                </button>
                <button
                  v-if="canCreateChild(group) && group.level < 10"
                  class="action-btn"
                  :title="t('grossanlass.planung.ressorts.addChild')"
                  @click="openCreateModal(group.id)"
                >
                  <v-icon icon="mdi-plus" size="16" />
                </button>
                <button
                  v-if="canManageMembersForGroup(group)"
                  class="action-btn"
                  :title="t('grossanlass.planung.ressorts.manageMembers')"
                  @click="openMembersModal(group)"
                >
                  <v-icon icon="mdi-account-plus-outline" size="16" />
                </button>
                <button
                  v-if="canEditGroup(group)"
                  class="action-btn"
                  :title="t('common.edit')"
                  @click="openEditModal(group)"
                >
                  <v-icon icon="mdi-pencil-outline" size="16" />
                </button>
                <button
                  v-if="canDeleteGroup(group)"
                  class="action-btn action-btn-danger"
                  :title="t('common.delete')"
                  @click="handleDelete(group)"
                >
                  <v-icon icon="mdi-delete-outline" size="16" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="canCreateRoot()" class="group-row group-row--add-root">
            <td class="col-name" :colspan="showManagementActions ? 3 : 2">
              <button type="button" class="add-root-btn" @click="openCreateModal()">
                <v-icon icon="mdi-plus" size="18" />
                {{ t('grossanlass.planung.ressorts.addAction') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>

    <div v-else class="ressorts-subtab">
          <EFilterRow v-if="uniqueMembers.length" class="members-filter-row">
            <v-col class="e-filter-row__search">
              <ESearchField
                v-model="memberSearchQuery"
                :label="t('grossanlass.planung.ressorts.membersSearchPlaceholder')"
              />
            </v-col>
            <v-col cols="auto" class="e-filter-row__select">
              <select
                v-model="memberRoleFilter"
                class="form-select role-select-sm"
                :aria-label="t('common.role')"
              >
                <option value="">{{ t('grossanlass.planung.ressorts.membersFilterAllRoles') }}</option>
                <option
                  v-for="item in memberRoleFilterItems"
                  :key="item.value"
                  :value="item.value"
                >
                  {{ item.title }}
                </option>
              </select>
            </v-col>
            <v-col cols="auto" class="e-filter-row__actions members-sort-actions">
              <SortHeaderButton
                :label="t('grossanlass.planung.ressorts.membersSortName')"
                :title="memberSortDir === 'asc'
                  ? t('grossanlass.planung.ressorts.membersSortNameAsc')
                  : t('grossanlass.planung.ressorts.membersSortNameDesc')"
                sort-key="name"
                active-key="name"
                :direction="memberSortDir"
                @toggle="toggleMemberNameSort"
              />
              <EButton
                variant="text"
                size="small"
                :style="{ visibility: hasMemberListFilters ? 'visible' : 'hidden' }"
                :aria-hidden="!hasMemberListFilters"
                @click="resetMemberListFilters"
              >
                {{ t('grossanlass.planung.ressorts.membersResetFilters') }}
              </EButton>
            </v-col>
          </EFilterRow>
          <ul v-if="filteredUniqueMembers.length" class="member-overview">
            <li v-for="row in filteredUniqueMembers" :key="row.groupMember.user_id" class="member-overview__row">
              <DepartmentMemberRow
                :name="row.departmentMember?.name || row.groupMember.name"
                :subtitle="row.groups.join(' · ')"
                :avatar="row.groupMember"
                :dept-stage-role="row.departmentMember?.role"
                :can-manage="!!row.departmentMember && canManageMember(row.departmentMember)"
                @details="openMemberDetail(row.departmentMember)"
                @remove="handleRemoveFromDepartment(row.departmentMember)"
              >
                <template #aside>
                  <select
                    v-if="row.departmentMember && canEditDeptRole(row.departmentMember.user_id)"
                    :value="normalizeDeptRole(row.departmentMember.role)"
                    class="form-select role-select-sm"
                    :aria-label="t('common.role')"
                    @change="handleDeptRoleChange(row.departmentMember.user_id, ($event.target as HTMLSelectElement).value)"
                  >
                    <option
                      v-for="item in roleSelectItemsFor(row.departmentMember.role)"
                      :key="item.value"
                      :value="item.value"
                    >
                      {{ item.title }}
                    </option>
                  </select>
                  <span v-else class="role-readonly">{{ deptRoleLabelFor(row.groupMember.user_id) }}</span>
                </template>
              </DepartmentMemberRow>
            </li>
          </ul>
          <p v-else class="text-muted">
            {{
              uniqueMembers.length
                ? t('grossanlass.planung.ressorts.emptyMembersFilter')
                : t('grossanlass.planung.ressorts.emptyMembersPanel')
            }}
          </p>
    </div>
    </template>

    <EDialog
      v-model="showGroupModal"
      :max-width="showProjectWindow || showAreaMap ? 920 : 480"
      :title="groupModalTitle"
      scrollable
    >
      <ETextField
        ref="groupNameInput"
        v-model="groupForm.name"
        :label="groupNameLabel"
        :placeholder="groupNamePlaceholder"
        hide-details="auto"
      />
      <ESelect
        v-if="showChildKindSelect"
        v-model="groupForm.kind"
        :items="childKindSelectItems"
        :label="t('grossanlass.planung.ressorts.childKindLabel')"
        hide-details
      />
      <ESelect
        v-if="canManageStruktur"
        v-model="groupForm.parent_id"
        :items="parentGroupSelectItems"
        :label="t('grossanlass.planung.ressorts.parentLabel')"
        :disabled="!!fixedParentId && !editingGroup"
        hide-details
      />
      <EDateRangeField
        v-if="showProjectWindow"
        :department-id="departmentId"
        :label="t('grossanlass.planung.ressorts.windowLabel')"
        v-model:start="groupForm.window_start"
        v-model:end="groupForm.window_end"
        allow-past
        show-presets
        preset-mode="fixed-periods"
      />
      <p v-if="showProjectWindow" class="window-hint">{{ t('grossanlass.planung.ressorts.windowHint') }}</p>
      <ETextarea
        v-if="showProjectWindow || editingGroup"
        v-model="groupForm.description"
        :label="t('grossanlass.planung.ressorts.descriptionHeading')"
        :placeholder="t('grossanlass.planung.ressorts.descriptionPlaceholder')"
        rows="3"
        hide-details="auto"
      />
      <ESwitch
        v-if="showAreaMapToggle"
        v-model="groupForm.include_on_map"
        :label="t('grossanlass.planung.ressorts.includeOnMap')"
        :hint="t('grossanlass.planung.ressorts.includeOnMapHint')"
        persistent-hint
        hide-details="auto"
      />
      <div v-if="showProjectWindow || showAreaMap" class="group-modal-map">
        <p v-if="!showAreaMap" class="group-modal-map__hint">
          {{ t('grossanlass.planung.ressorts.mapHint') }}
        </p>
        <ActivityVenueOverviewBlock
          v-if="venueAddressId"
          ref="groupMapRef"
          :venue-address-id="venueAddressId"
          :department-id="departmentId"
          :ga-department-id="departmentId"
          ga-map-mode="all"
          inline-place-draft
          :draft-place-name="groupForm.name"
          :draft-place-kind="showAreaMap ? 'area' : 'bauprojekt'"
          hide-title
          @save-area="saveAreaKeepOpen"
        />
        <p v-else class="group-modal-map__missing">{{ t('grossanlass.planung.ressorts.placeMissing') }}</p>
      </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeGroupModal">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!groupForm.name.trim() || isSaving"
          :loading="isSaving"
          @click="saveGroup"
        >
          {{ isSaving ? t('grossanlass.planung.ressorts.saving') : (editingGroup ? t('common.save') : t('common.create')) }}
        </EButton>
      </template>
    </EDialog>

    <EDialog v-model="showMembersModal" :max-width="1024" card-class="ga-members-modal">
      <template #title>
        <template v-if="selectedGroup">
          {{ t('grossanlass.planung.ressorts.membersHeading') }}
          <strong>{{ selectedGroup.name }}</strong>
        </template>
      </template>
      <template v-if="selectedGroup">
        <div class="members-section">
          <h4 class="section-title">
            {{
              selectedGroup.members.length > 0
                ? t('grossanlass.planung.ressorts.sectionCurrentMembers', { count: selectedGroup.members.length })
                : t('grossanlass.planung.ressorts.membersTableHeading')
            }}
          </h4>
          <p v-if="canManageStruktur" class="members-role-hint">{{ t('grossanlass.planung.ressorts.roleHint') }}</p>
          <table class="members-table">
            <thead>
              <tr>
                <th>{{ t('common.name') }}</th>
                <th>{{ t('settings.groups.memberColEmail') }}</th>
                <th>{{ t('common.role') }}</th>
                <th>{{ t('grossanlass.planung.ressorts.colGroupLeader') }}</th>
                <th v-if="canFullyManage">{{ t('grossanlass.planung.ressorts.colProcure') }}</th>
                <th>
                  <span :title="t('grossanlass.planung.ressorts.primaryHomeHint')">
                    {{ t('grossanlass.planung.ressorts.primaryHome') }}
                  </span>
                </th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="selectedGroup.members.length === 0">
                <td class="empty-members-cell" :colspan="membersTableColspan">
                  {{ t('grossanlass.planung.ressorts.emptyNoMembers') }}
                </td>
              </tr>
              <tr v-for="member in selectedGroup.members" :key="member.user_id">
                <td class="member-name">
                  <UserAvatarBadge
                    :user="member"
                    :dept-stage-role="deptRoleForUser(member.user_id)"
                  />
                  <span class="name-text">{{ member.name }}</span>
                </td>
                <td class="member-email">{{ member.email }}</td>
                <td class="member-dept-role">
                  <select
                    v-if="canEditDeptRole(member.user_id)"
                    :value="groupMemberAccessValue(member)"
                    class="form-select role-select-sm"
                    @change="handleGroupMemberAccessChange(member, ($event.target as HTMLSelectElement).value)"
                  >
                    <option
                      v-for="item in groupMemberAccessItems(member)"
                      :key="item.value"
                      :value="item.value"
                    >
                      {{ item.title }}
                    </option>
                  </select>
                  <span v-else class="role-readonly">
                    {{ deptRoleLabelFor(member.user_id) }}
                  </span>
                </td>
                <td>
                  <span
                    v-if="memberSkipsGroupFlags(member.user_id)"
                    class="role-readonly role-implicit"
                    :title="t('grossanlass.planung.ressorts.roleImplicitViaDept')"
                  >
                    {{ t('grossanlass.planung.ressorts.roleImplicitViaDept') }}
                  </span>
                  <button
                    v-else-if="canManageMembersForGroup(selectedGroup)"
                    type="button"
                    class="flag-toggle"
                    :class="{ 'is-on': member.is_leader }"
                    :title="t('grossanlass.planung.ressorts.colGroupLeader')"
                    :aria-pressed="member.is_leader"
                    @click="handleRoleChange(member, member.is_leader ? 'member' : 'leader')"
                  >
                    ★
                  </button>
                  <span v-else class="role-readonly">{{ member.is_leader ? '★' : '—' }}</span>
                </td>
                <td v-if="canFullyManage">
                  <span
                    v-if="memberSkipsGroupFlags(member.user_id)"
                    class="role-readonly role-implicit"
                    :title="t('grossanlass.planung.ressorts.roleImplicitViaDept')"
                  >
                    {{ t('grossanlass.planung.ressorts.roleImplicitViaDept') }}
                  </span>
                  <label v-else class="procure-toggle">
                    <input
                      type="checkbox"
                      :checked="!!member.can_procure"
                      @change="handleCanProcureChange(member, ($event.target as HTMLInputElement).checked)"
                    />
                    <span>{{ t('grossanlass.planung.ressorts.canProcureShort') }}</span>
                  </label>
                </td>
                <td>
                  <button
                    v-if="canManageMembersForGroup(selectedGroup)"
                    type="button"
                    class="flag-toggle"
                    :class="{ 'is-on': member.is_primary }"
                    :title="t('grossanlass.planung.ressorts.primaryHome')"
                    :aria-pressed="member.is_primary"
                    @click="handlePrimaryChange(member, !member.is_primary)"
                  >
                    ⌂
                  </button>
                  <span v-else class="role-readonly">{{ member.is_primary ? '⌂' : '—' }}</span>
                </td>
                <td class="member-row-actions">
                  <EButton
                    v-if="canOpenMemberDetail(member.user_id)"
                    variant="secondary"
                    size="small"
                    @click="openMemberDetailById(member.user_id)"
                  >
                    {{ t('settings.departmentUsers.memberDetails') }}
                  </EButton>
                  <button
                    v-if="canManageMembersForGroup(selectedGroup)"
                    class="action-btn action-btn-danger"
                    :title="t('common.remove')"
                    @click="handleRemoveMember(member)"
                  >
                    <v-icon icon="mdi-close" size="14" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <details v-if="canManageMembersForGroup(selectedGroup)" class="member-modal-accordion">
          <summary class="member-modal-accordion__summary">
            {{ t('grossanlass.planung.ressorts.helperHeading') }}
          </summary>
          <div class="member-modal-accordion__body">
            <GrossanlassHelperInviteForm
              :department-id="departmentId"
              :groups="groups"
              :fixed-group-id="selectedGroup.id"
              @created="onHelperCreated"
            />
          </div>
        </details>

        <details v-if="canManageMembersForGroup(selectedGroup)" class="member-modal-accordion">
          <summary class="member-modal-accordion__summary">
            {{ t('grossanlass.planung.ressorts.addMemberHeading') }}
          </summary>
          <div class="member-modal-accordion__body add-member-section">
          <div v-if="canManageStruktur" class="add-member-role-row">
            <label class="add-member-role-label" for="ga-add-member-dept-role">{{ t('common.role') }}</label>
            <select
              v-if="canAssignDeptRoles"
              id="ga-add-member-dept-role"
              v-model="addMemberForm.deptRole"
              class="form-select role-select-sm"
            >
              <option
                v-for="item in editRoleSelectItems"
                :key="item.value"
                :value="item.value"
              >
                {{ item.title }}
              </option>
            </select>
            <span v-else class="role-readonly">{{ getRoleLabel(addMemberForm.deptRole) }}</span>
            <template v-if="!addMemberSkipsGroupFlags">
              <label class="add-member-role-label" for="ga-add-member-group-role">
                {{ t('grossanlass.planung.ressorts.colGroupLeader') }}
              </label>
              <select
                id="ga-add-member-group-role"
                v-model="addMemberForm.groupRole"
                class="form-select role-select-sm"
              >
                <option value="member">{{ t('settings.groups.roleMember') }}</option>
                <option value="leader">{{ t('settings.groups.roleLeader') }}</option>
              </select>
              <template v-if="canFullyManage">
                <label class="procure-toggle add-member-procure">
                  <input v-model="addMemberForm.can_procure" type="checkbox" />
                  <span>
                    <strong>{{ t('grossanlass.planung.ressorts.colProcure') }}</strong>
                    — {{ t('grossanlass.planung.ressorts.canProcureShort') }}
                  </span>
                </label>
                <p class="add-member-procure-hint">{{ t('grossanlass.planung.ressorts.canProcureAddHint') }}</p>
              </template>
            </template>
            <p v-else class="add-member-procure-hint">{{ t('grossanlass.planung.ressorts.roleImplicitAddHint') }}</p>
          </div>
          <div v-if="isLoadingUsers" class="loading-inline">
            <div class="spinner-sm"></div>
            <span>{{ t('settings.groups.loadingUsers') }}</span>
          </div>
          <template v-else>
            <p v-if="unassignedUsers.length === 0" class="no-users-hint">
              {{ t('settings.groups.allUsersAssigned') }}
            </p>
            <ul v-else class="candidate-list">
              <li v-for="user in unassignedUsers" :key="user.user_id" class="candidate-row">
                <div class="candidate-meta">
                  <strong>{{ user.name }}</strong>
                  <span>{{ user.email }}</span>
                </div>
                <button
                  type="button"
                  class="action-btn action-btn-add"
                  :title="t('grossanlass.planung.ressorts.addMemberPlus')"
                  :disabled="addingUserId === user.user_id"
                  @click="handleAddMember(user.user_id)"
                >
                  <v-icon icon="mdi-plus" size="18" />
                </button>
              </li>
            </ul>
          </template>

          <div class="outside-dept-block">
            <h5 class="subsection-title">{{ t('grossanlass.planung.ressorts.addOutsideHeading') }}</h5>
            <p class="outside-dept-hint">{{ t('grossanlass.planung.ressorts.addOutsideHint') }}</p>
            <ETextField
              v-model="outsideSearchQuery"
              :label="t('grossanlass.planung.ressorts.addOutsideSearch')"
              :placeholder="t('grossanlass.planung.ressorts.addOutsidePlaceholder')"
              hide-details="auto"
              clearable
              autocomplete="off"
            />
            <p
              v-if="outsideSearchTrimmed.length > 0 && outsideSearchTrimmed.length < 3"
              class="add-user-search-hint"
            >
              {{ t('settings.departmentUsers.autocompleteCharsHint', { n: 3 - outsideSearchTrimmed.length }) }}
            </p>
            <div v-if="isLoadingOutside" class="loading-inline">
              <div class="spinner-sm"></div>
              <span>{{ t('settings.departmentUsers.modalLoadingAvailable') }}</span>
            </div>
            <ul v-else-if="outsideCandidates.length > 0" class="candidate-list">
              <li v-for="user in outsideCandidates" :key="user.id" class="candidate-row">
                <div class="candidate-meta">
                  <strong>{{ user.name }}</strong>
                  <span>{{ user.email }}</span>
                  <span class="candidate-dept">
                    {{
                      user.departments_label?.trim()
                        || user.primary_department_name?.trim()
                        || t('settings.departmentUsers.autocompleteNoDepartment')
                    }}
                  </span>
                </div>
                <button
                  type="button"
                  class="action-btn action-btn-add"
                  :title="t('grossanlass.planung.ressorts.addOutsidePlus')"
                  :disabled="addingUserId === user.id"
                  @click="handleAddOutsideUser(user)"
                >
                  <v-icon icon="mdi-plus" size="18" />
                </button>
              </li>
            </ul>
            <p
              v-else-if="outsideSearchTrimmed.length >= 3 && !isLoadingOutside"
              class="no-users-hint"
            >
              {{ t('grossanlass.planung.ressorts.addOutsideEmpty') }}
            </p>
          </div>
          </div>
        </details>
      </template>
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeMembersModal">{{ t('settings.groups.close') }}</EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="showAddMemberDialog"
      :max-width="640"
      :title="t('grossanlass.planung.ressorts.addMembersAction')"
    >
      <GrossanlassHelperInviteForm
        :department-id="departmentId"
        :groups="groups"
        @created="onHelperCreated"
      />
      <div v-if="canManageStruktur && addMemberGroupSelectItems.length" class="add-overview-existing">
        <ESelect
          v-model="addMemberTargetGroupId"
          :items="addMemberGroupSelectItems"
          :label="t('grossanlass.planung.ressorts.helperRessort')"
          hide-details
        />
        <details v-if="selectedGroup" class="member-modal-accordion" open>
          <summary class="member-modal-accordion__summary">
            {{ t('grossanlass.planung.ressorts.addMemberHeading') }}
          </summary>
          <div class="member-modal-accordion__body add-member-section">
            <div class="add-member-role-row">
              <label class="add-member-role-label" for="ga-overview-add-member-dept-role">{{ t('common.role') }}</label>
              <select
                v-if="canAssignDeptRoles"
                id="ga-overview-add-member-dept-role"
                v-model="addMemberForm.deptRole"
                class="form-select role-select-sm"
              >
                <option
                  v-for="item in editRoleSelectItems"
                  :key="item.value"
                  :value="item.value"
                >
                  {{ item.title }}
                </option>
              </select>
              <span v-else class="role-readonly">{{ getRoleLabel(addMemberForm.deptRole) }}</span>
            </div>
            <div v-if="isLoadingUsers" class="loading-inline">
              <div class="spinner-sm"></div>
              <span>{{ t('settings.groups.loadingUsers') }}</span>
            </div>
            <ul v-else-if="unassignedUsers.length" class="candidate-list">
              <li v-for="user in unassignedUsers" :key="user.user_id" class="candidate-row">
                <div class="candidate-meta">
                  <strong>{{ user.name }}</strong>
                  <span>{{ user.email }}</span>
                </div>
                <button
                  type="button"
                  class="action-btn action-btn-add"
                  :title="t('grossanlass.planung.ressorts.addMemberPlus')"
                  :disabled="addingUserId === user.user_id"
                  @click="handleAddMember(user.user_id)"
                >
                  <v-icon icon="mdi-plus" size="18" />
                </button>
              </li>
            </ul>
            <p v-else class="no-users-hint">{{ t('settings.groups.allUsersAssigned') }}</p>
          </div>
        </details>
      </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeAddMemberDialog">
          {{ t('settings.groups.close') }}
        </EButton>
      </template>
    </EDialog>

    <DepartmentMemberDetailDialog
      v-model="showMemberDetail"
      :member="editingMember"
      :department-id="departmentId"
      :is-grossanlass="true"
      hide-js-coach
      membership-accordion-open
      @saved="onMemberDetailSaved"
      @removed="onMemberDetailSaved"
    />

    <EDialog
      v-model="showProjectModal"
      :max-width="920"
      :title="projectModalTitle"
    >
      <GrossanlassBauprojektPanel
        v-if="projectGroup && departmentId"
        :department-id="departmentId"
        :group-id="projectGroup.id"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="showProjectModal = false">
          {{ t('settings.groups.close') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'
import {
  DepartmentMemberDetailDialog,
  DepartmentMemberRow,
} from '@/components/members'
import { useDepartmentMemberAdmin } from '@/composables/useDepartmentMemberAdmin'
import GrossanlassHelperInviteForm from '@/components/grossanlass/GrossanlassHelperInviteForm.vue'
import GrossanlassBauprojektPanel from '@/components/grossanlass/GrossanlassBauprojektPanel.vue'
import GrossanlassGroupNodeIcon from '@/components/grossanlass/GrossanlassGroupNodeIcon.vue'
import ActivityVenueOverviewBlock from '@/components/activities/ActivityVenueOverviewBlock.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import EFilterRow from '@/components/layout/EFilterRow.vue'
import SortHeaderButton from '@/components/material/SortHeaderButton.vue'
import { EButton, EDateRangeField, EDialog, ESearchField, ESwitch, ETextField, ESelect, ETextarea } from '@/components/form/base'
import '@/styles/views/materials-view-tabs.css'
import {
  getGrossanlassGroups,
  createGrossanlassGroup,
  updateGrossanlassGroup,
  deleteGrossanlassGroup,
  addGrossanlassGroupMember,
  updateGrossanlassGroupMember,
  removeGrossanlassGroupMember,
  createGrossanlassHelper,
  type GrossanlassGroup,
  type GrossanlassGroupKind,
} from '@/api/grossanlassGroups'
import {
  getDepartmentMembers,
  getAvailableUsersForDepartment,
  updateDepartmentMember,
  type AvailableUser,
  type DepartmentMember,
} from '@/api/departments'
import type { GroupMember } from '@/api/groups'
import { filterAvailableUsersByQuery } from '@/utils/availableUserSearch'
import { textMatchesAllTokens } from '@/utils/searchHighlight'
import {
  flattenGrossanlassGroupsWithLevel,
  grossanlassGroupSelectTitle,
} from '@/utils/grossanlassGroupHierarchy'
import { grossanlassGroupNodeKindKey } from '@/utils/grossanlassGroupNode'
import { gaDeptRoleSkipsGroupFlags } from '@/utils/grossanlassAccess'
import { formatBauprojektWindow } from '@/utils/grossanlassBauprojektWindow'
import { getDeptRoleShort, normalizeDeptRole, ROLE_HIERARCHY_GROSSANLASS } from '@/utils/departmentMemberRoles'
import { getGrossanlassPlanung, updateGrossanlassPlanung } from '@/api/grossanlassPlanung'
import { updateGrossanlassPlace } from '@/api/grossanlassLogistics'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')

const {
  canManageMember,
  removeFromDepartment,
  editRoleSelectItems,
  roleSelectItemsFor,
  getRoleLabel,
} = useDepartmentMemberAdmin(departmentId, () => true)

const canAssignDeptRoles = computed(() => editRoleSelectItems.value.length > 0)

const groups = ref<GrossanlassGroup[]>([])
const logisticsGroupId = ref<string | null>(null)
const isSavingLogistics = ref(false)
const isLoading = ref(false)
const error = ref<string | null>(null)
const activeSubTab = ref<'ressorts' | 'members'>('ressorts')
const showAddMemberDialog = ref(false)
const addMemberTargetGroupId = ref<string | null>(null)

const showGroupModal = ref(false)
const editingGroup = ref<GrossanlassGroup | null>(null)
const fixedParentId = ref<string | null>(null)
const isSaving = ref(false)
const groupNameInput = ref<{ focus?: () => void } | null>(null)
const groupForm = ref({
  name: '',
  include_on_map: false,
  parent_id: null as string | null,
  kind: 'ressort' as GrossanlassGroupKind,
  window_start: '',
  window_end: '',
  description: '',
})
const showProjectModal = ref(false)
const projectGroup = ref<GrossanlassGroup | null>(null)
const venueAddressId = ref<string | null>(null)
const groupMapRef = ref<InstanceType<typeof ActivityVenueOverviewBlock> | null>(null)

const showMembersModal = ref(false)
const selectedGroup = ref<GrossanlassGroup | null>(null)
const departmentMembers = ref<DepartmentMember[]>([])
const isLoadingUsers = ref(false)
const addMemberForm = ref({ groupRole: 'member', deptRole: 'u', can_procure: false })
const addingUserId = ref<string | null>(null)
const outsideSearchQuery = ref('')
const outsideUsers = ref<AvailableUser[]>([])
const isLoadingOutside = ref(false)
let outsideSearchTimer: ReturnType<typeof setTimeout> | null = null
const showMemberDetail = ref(false)
const editingMember = ref<DepartmentMember | null>(null)

const outsideSearchTrimmed = computed(() => outsideSearchQuery.value.trim())
const outsideCandidates = computed(() =>
  filterAvailableUsersByQuery(outsideUsers.value, outsideSearchTrimmed.value).slice(0, 12),
)

const {
  canFullyManage,
  canManageStruktur,
  canCreateRoot,
  canCreateChild,
  canEditGroup,
  canDeleteGroup,
  canManageMembersForGroup,
  isBereichsleitung,
  showManagementActions,
} = useGrossanlassRessortScope(groups)

const membersTableColspan = computed(() => (canFullyManage.value ? 7 : 6))

const addMemberGroupSelectItems = computed(() =>
  hierarchicalGroups.value
    .filter((group) => canManageMembersForGroup(group))
    .map((group) => ({
      title: grossanlassGroupSelectTitle(group, t('grossanlass.planung.ressorts.kindBauprojekt')),
      value: group.id,
    })),
)

const canAddOverviewMembers = computed(
  () =>
    groups.value.length > 0 &&
    (canManageStruktur.value || isBereichsleitung.value) &&
    addMemberGroupSelectItems.value.length > 0,
)

const addMemberSkipsGroupFlags = computed(() =>
  gaDeptRoleSkipsGroupFlags(addMemberForm.value.deptRole),
)

const rootCount = computed(() => groups.value.filter((g) => !g.parent_id).length)
const totalMembers = computed(() => groups.value.reduce((sum, g) => sum + g.member_count, 0))

const hierarchicalGroups = computed(() => flattenGrossanlassGroupsWithLevel(groups.value))

const uniqueMembers = computed(() => {
  const deptById = new Map(departmentMembers.value.map((m) => [m.user_id, m]))
  const map = new Map<
    string,
    {
      groupMember: GroupMember
      departmentMember: DepartmentMember | null
      groups: string[]
      isLeader: boolean
      isPrimary: boolean
      isProcure: boolean
    }
  >()
  for (const group of groups.value) {
    for (const member of group.members ?? []) {
      const row = map.get(member.user_id)
      if (row) {
        if (!row.groups.includes(group.name)) row.groups.push(group.name)
        if (member.is_leader) row.isLeader = true
        if (member.is_primary) row.isPrimary = true
        if (member.can_procure) row.isProcure = true
      } else {
        map.set(member.user_id, {
          groupMember: member,
          departmentMember: deptById.get(member.user_id) ?? null,
          groups: [group.name],
          isLeader: !!member.is_leader,
          isPrimary: !!member.is_primary,
          isProcure: !!member.can_procure,
        })
      }
    }
  }
  return [...map.values()].sort((a, b) =>
    a.groupMember.name.localeCompare(b.groupMember.name, 'de'),
  )
})

const memberSearchQuery = ref('')
const memberRoleFilter = ref('')
const memberSortDir = ref<'asc' | 'desc'>('asc')

type UniqueMemberRow = (typeof uniqueMembers.value)[number]

function memberRowName(row: UniqueMemberRow): string {
  return row.departmentMember?.name || row.groupMember.name
}

function memberRowRole(row: UniqueMemberRow): string {
  return normalizeDeptRole(row.departmentMember?.role || 'u')
}

function memberRowHaystack(row: UniqueMemberRow): string {
  const member = row.departmentMember
  return [
    memberRowName(row),
    member?.nickname,
    member?.first_name,
    member?.last_name,
    member?.email,
    row.groupMember.name,
    row.groupMember.nickname,
    row.groupMember.email,
    ...row.groups,
    getDeptRoleShort(memberRowRole(row), true),
    getRoleLabel(memberRowRole(row)),
  ]
    .filter((part) => part != null && String(part).trim() !== '')
    .join(' ')
}

const memberRoleFilterItems = computed(() => {
  const present = new Set(uniqueMembers.value.map((row) => memberRowRole(row)))
  return ROLE_HIERARCHY_GROSSANLASS.filter((key) => present.has(key)).map((key) => ({
    value: key,
    title: `${getDeptRoleShort(key, true)} – ${getRoleLabel(key)}`,
  }))
})

const hasMemberListFilters = computed(
  () => memberSearchQuery.value.trim() !== '' || memberRoleFilter.value !== '',
)

const filteredUniqueMembers = computed(() => {
  let rows = uniqueMembers.value
  if (memberSearchQuery.value.trim()) {
    rows = rows.filter((row) => textMatchesAllTokens(memberRowHaystack(row), memberSearchQuery.value))
  }
  if (memberRoleFilter.value) {
    rows = rows.filter((row) => memberRowRole(row) === memberRoleFilter.value)
  }
  const direction = memberSortDir.value === 'asc' ? 1 : -1
  return [...rows].sort((a, b) => direction * memberRowName(a).localeCompare(memberRowName(b), 'de'))
})

function toggleMemberNameSort() {
  memberSortDir.value = memberSortDir.value === 'asc' ? 'desc' : 'asc'
}

function resetMemberListFilters() {
  memberSearchQuery.value = ''
  memberRoleFilter.value = ''
}

const availableParents = computed(() => {
  if (!editingGroup.value) {
    return hierarchicalGroups.value
  }
  const excludeIds = new Set<string>()
  excludeIds.add(editingGroup.value.id)
  function collectChildIds(parentId: string) {
    for (const g of groups.value) {
      if (g.parent_id === parentId) {
        excludeIds.add(g.id)
        collectChildIds(g.id)
      }
    }
  }
  collectChildIds(editingGroup.value.id)
  return hierarchicalGroups.value.filter((g) => !excludeIds.has(g.id))
})

const parentGroupSelectItems = computed(() => [
  { title: t('grossanlass.planung.ressorts.parentNone'), value: null },
  ...availableParents.value.map((g) => ({
    title: grossanlassGroupSelectTitle(g, t('grossanlass.planung.ressorts.kindBauprojekt')),
    value: g.id,
  })),
])

const groupModalTitle = computed(() => {
  if (editingGroup.value) return t('grossanlass.planung.ressorts.modalEdit')
  if (fixedParentId.value || groupForm.value.parent_id) {
    return groupForm.value.kind === 'ressort'
      ? t('grossanlass.planung.ressorts.modalNewUnterressort')
      : t('grossanlass.planung.ressorts.modalNewBauprojekt')
  }
  return t('grossanlass.planung.ressorts.modalNewRessort')
})

const showChildKindSelect = computed(() => {
  if (editingGroup.value) {
    return !!editingGroup.value.parent_id && canEditGroup(editingGroup.value)
  }
  return !!(fixedParentId.value || groupForm.value.parent_id)
})

const showProjectWindow = computed(() => {
  const hasParent = !!(fixedParentId.value || groupForm.value.parent_id || editingGroup.value?.parent_id)
  if (!hasParent) return false
  return groupForm.value.kind === 'teilbereich' || editingGroup.value?.node_type === 'bauprojekt'
})

const showAreaMapToggle = computed(() => !showProjectWindow.value)

const showAreaMap = computed(() => showAreaMapToggle.value && groupForm.value.include_on_map)

const projectModalTitle = computed(() =>
  projectGroup.value
    ? t('grossanlass.planung.ressorts.projectTitle', { name: projectGroup.value.name })
    : t('grossanlass.planung.ressorts.openProject'),
)

const childKindSelectItems = computed(() => [
  {
    title: t('grossanlass.planung.ressorts.kindUnterressort'),
    value: 'ressort' as GrossanlassGroupKind,
  },
  {
    title: t('grossanlass.planung.ressorts.kindBauprojekt'),
    value: 'teilbereich' as GrossanlassGroupKind,
  },
])

const groupNameLabel = computed(() => {
  if (!fixedParentId.value && !groupForm.value.parent_id && !editingGroup.value?.parent_id) {
    return t('grossanlass.planung.ressorts.nameLabelRessort')
  }
  return groupForm.value.kind === 'ressort' || editingGroup.value?.kind === 'ressort'
    ? t('grossanlass.planung.ressorts.nameLabelUnterressort')
    : t('grossanlass.planung.ressorts.nameLabelBauprojekt')
})

const groupNamePlaceholder = computed(() => {
  if (!fixedParentId.value && !groupForm.value.parent_id && !editingGroup.value?.parent_id) {
    return t('grossanlass.planung.ressorts.namePlaceholderRessort')
  }
  return groupForm.value.kind === 'ressort' || editingGroup.value?.kind === 'ressort'
    ? t('grossanlass.planung.ressorts.namePlaceholderUnterressort')
    : t('grossanlass.planung.ressorts.namePlaceholderBauprojekt')
})

const unassignedUsers = computed(() => {
  if (!selectedGroup.value) return []
  const assignedIds = new Set(selectedGroup.value.members.map((m) => m.user_id))
  return departmentMembers.value.filter((u) => !assignedIds.has(u.user_id))
})

function projectWindow(group: GrossanlassGroup): string {
  return formatBauprojektWindow(group.window_start, group.window_end)
}

function openProjectPanel(group: GrossanlassGroup) {
  projectGroup.value = group
  showProjectModal.value = true
}

function kindLabel(group: GrossanlassGroup): string {
  return t(grossanlassGroupNodeKindKey(group.node_type))
}

function isCostEligible(group: GrossanlassGroup): boolean {
  return group.node_type !== 'bauprojekt' && group.kind !== 'teilbereich'
}

function isLogisticsNode(group: GrossanlassGroup): boolean {
  return logisticsGroupId.value === group.id
}

function canSetLogisticsNode(group: GrossanlassGroup): boolean {
  return canFullyManage.value && !logisticsGroupId.value && isCostEligible(group)
}

function getGroupMembersForDisplay(group: GrossanlassGroup): GroupMember[] {
  const leaders = group.members.filter((m) => m.is_leader)
  const members = group.members.filter((m) => !m.is_leader)
  return [...leaders, ...members]
}

function deptRoleForUser(userId: string): string | null {
  return departmentMembers.value.find((m) => m.user_id === userId)?.role ?? null
}

function memberSkipsGroupFlags(userId: string): boolean {
  return gaDeptRoleSkipsGroupFlags(deptRoleForUser(userId))
}

function memberShowProcure(userId: string, canProcure?: boolean): boolean {
  return !!canProcure && !memberSkipsGroupFlags(userId)
}

async function loadGroups() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = null
  try {
    const [groupList, planung] = await Promise.all([
      getGrossanlassGroups(departmentId.value),
      getGrossanlassPlanung(departmentId.value),
    ])
    groups.value = groupList
    logisticsGroupId.value = planung.config.logistics_group_id || null
    void loadDepartmentMembers()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    error.value = e.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad')
  } finally {
    isLoading.value = false
  }
}

async function setLogisticsNode(group: GrossanlassGroup) {
  if (!departmentId.value || isSavingLogistics.value) return
  isSavingLogistics.value = true
  try {
    const next = await updateGrossanlassPlanung(departmentId.value, {
      logistics_group_id: group.id,
    })
    logisticsGroupId.value = next.config.logistics_group_id || group.id
    toast.success(t('grossanlass.planung.ressorts.costSetToast', { name: group.name }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    isSavingLogistics.value = false
  }
}

async function clearLogisticsNode() {
  if (!canFullyManage.value || !departmentId.value || isSavingLogistics.value) return
  const ok = await confirm.confirm({
    title: t('grossanlass.planung.ressorts.costClearTitle'),
    message: t('grossanlass.planung.ressorts.costClearMessage'),
    confirmText: t('grossanlass.planung.ressorts.costClearConfirm'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
  isSavingLogistics.value = true
  try {
    await updateGrossanlassPlanung(departmentId.value, { logistics_group_id: null })
    logisticsGroupId.value = null
    toast.success(t('grossanlass.planung.ressorts.costClearToast'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    isSavingLogistics.value = false
  }
}

async function loadDepartmentMembers() {
  if (!departmentId.value) return
  isLoadingUsers.value = true
  try {
    departmentMembers.value = await getDepartmentMembers(departmentId.value)
  } catch {
    // ignore
  } finally {
    isLoadingUsers.value = false
  }
}

function deptRoleLabelFor(userId: string): string {
  const member = deptMemberFor(userId)
  if (!member) return '—'
  const short = getDeptRoleShort(member.role, true)
  return short ? `${short} – ${getRoleLabel(member.role)}` : getRoleLabel(member.role)
}

function canEditDeptRole(userId: string): boolean {
  if (!canAssignDeptRoles.value) return false
  const member = deptMemberFor(userId)
  return !!member && canManageMember(member)
}

function groupMemberAccessItems(member: GroupMember) {
  return roleSelectItemsFor(deptMemberFor(member.user_id)?.role)
}

function groupMemberAccessValue(member: GroupMember): string {
  return normalizeDeptRole(deptMemberFor(member.user_id)?.role || 'u')
}

async function handleGroupMemberAccessChange(member: GroupMember, value: string) {
  if (!departmentId.value || !selectedGroup.value || !canEditDeptRole(member.user_id)) return
  await handleDeptRoleChange(member.user_id, value)
}

async function handleDeptRoleChange(userId: string, role: string) {
  if (!departmentId.value || !canEditDeptRole(userId)) return
  try {
    await updateDepartmentMember(departmentId.value, userId, { role })
    await loadDepartmentMembers()
    if (
      gaDeptRoleSkipsGroupFlags(role) &&
      selectedGroup.value?.members.some(
        (m) => m.user_id === userId && (m.is_leader || m.can_procure),
      )
    ) {
      await updateGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, userId, {
        role: 'member',
        can_procure: false,
      })
    }
    await refreshSelectedGroup()
    toast.success(t('grossanlass.planung.ressorts.roleSaved'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorRoleSave'))
  }
}

async function applyAddMemberDeptRole(userId: string) {
  if (!departmentId.value || !canAssignDeptRoles.value) return
  const existing = deptMemberFor(userId)
  if (!existing || existing.role === addMemberForm.value.deptRole) return
  if (!canManageMember(existing)) return
  await updateDepartmentMember(departmentId.value, userId, { role: addMemberForm.value.deptRole })
  await loadDepartmentMembers()
}

function deptMemberFor(userId: string): DepartmentMember | undefined {
  return departmentMembers.value.find((m) => m.user_id === userId)
}

function canOpenMemberDetail(userId: string): boolean {
  const member = deptMemberFor(userId)
  return !!member && canManageMember(member)
}

function openMemberDetail(member: DepartmentMember | null | undefined) {
  if (!member || !canManageMember(member)) return
  editingMember.value = member
  showMemberDetail.value = true
}

function openMemberDetailById(userId: string) {
  openMemberDetail(deptMemberFor(userId))
}

async function handleRemoveFromDepartment(member: DepartmentMember | null | undefined) {
  if (!member) return
  const removed = await removeFromDepartment(member)
  if (!removed) return
  if (editingMember.value?.user_id === member.user_id) {
    showMemberDetail.value = false
    editingMember.value = null
  }
  await loadGroups()
}

async function onMemberDetailSaved() {
  showMemberDetail.value = false
  editingMember.value = null
  await loadGroups()
}

function openCreateModal(parentId: string | null = null) {
  editingGroup.value = null
  fixedParentId.value = parentId
  groupForm.value = {
    name: '',
    include_on_map: false,
    parent_id: parentId,
    kind: parentId && !canManageStruktur.value ? 'teilbereich' : 'ressort',
    window_start: '',
    window_end: '',
    description: '',
  }
  showGroupModal.value = true
  nextTick(() => {
    groupNameInput.value?.focus?.()
    void syncGroupMapPlacement()
  })
}

function openEditModal(group: GrossanlassGroup) {
  editingGroup.value = group
  fixedParentId.value = null
  groupForm.value = {
    name: group.name,
    include_on_map: group.include_on_map === true || group.place?.kind === 'area',
    parent_id: group.parent_id,
    kind: group.kind,
    window_start: group.window_start || '',
    window_end: group.window_end || '',
    description: group.description || '',
  }
  showGroupModal.value = true
  nextTick(() => {
    groupNameInput.value?.focus?.()
    void syncGroupMapPlacement()
  })
}

function closeGroupModal() {
  groupMapRef.value?.clearInlineDraft()
  showGroupModal.value = false
  editingGroup.value = null
  fixedParentId.value = null
}

async function loadVenueAddress() {
  if (!departmentId.value) {
    venueAddressId.value = null
    return
  }
  try {
    const pack = await getGrossanlassPlanung(departmentId.value)
    venueAddressId.value = pack.config.venue_address_id || null
  } catch {
    venueAddressId.value = null
  }
}

async function syncGroupMapPlacement() {
  if (!showGroupModal.value || !venueAddressId.value) return
  if (!showProjectWindow.value && !showAreaMap.value) return
  await nextTick()
  await groupMapRef.value?.reloadGa?.()
  await nextTick()
  groupMapRef.value?.refreshMaps?.()
  if (showAreaMap.value) {
    if (editingGroup.value?.place?.kind === 'area' && editingGroup.value.place.id) {
      groupMapRef.value?.beginEditPlace(editingGroup.value.place.id)
      return
    }
    groupMapRef.value?.beginInlineDraft(groupForm.value.name, 'area')
    return
  }
  if (editingGroup.value?.place?.id) {
    groupMapRef.value?.beginEditPlace(editingGroup.value.place.id)
    return
  }
  if (groupForm.value.kind === 'teilbereich') {
    groupMapRef.value?.beginInlineDraft(groupForm.value.name, 'bauprojekt')
  }
}


async function syncGroupPlaceCoords(group: GrossanlassGroup | null) {
  if (!departmentId.value || !group?.place?.id) return
  const coords = groupMapRef.value?.getInlineDraftCoords()
  if (coords?.latitude == null || coords?.longitude == null) return
  try {
    await updateGrossanlassPlace(departmentId.value, group.place.id, {
      latitude: coords.latitude,
      longitude: coords.longitude,
    })
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.einstellungen.mapMoveError'))
  }
}

async function persistGroup(closeAfter: boolean) {
  if (!groupForm.value.name.trim() || isSaving.value || !departmentId.value) return
  isSaving.value = true
  try {
    let saved: GrossanlassGroup
    const includeOnMap = showAreaMapToggle.value && groupForm.value.include_on_map
    const polygon = includeOnMap ? groupMapRef.value?.getInlineDraftPolygon() ?? [] : null
    if (editingGroup.value) {
      saved = await updateGrossanlassGroup(departmentId.value, editingGroup.value.id, {
        name: groupForm.value.name.trim(),
        parent_id: groupForm.value.parent_id,
        kind: editingGroup.value.parent_id ? groupForm.value.kind : undefined,
        window_start: showProjectWindow.value ? groupForm.value.window_start || null : undefined,
        window_end: showProjectWindow.value ? groupForm.value.window_end || null : undefined,
        description: groupForm.value.description.trim() || null,
        include_on_map: showAreaMapToggle.value ? includeOnMap : undefined,
        polygon: includeOnMap ? polygon : undefined,
      })
    } else {
      saved = await createGrossanlassGroup(departmentId.value, {
        name: groupForm.value.name.trim(),
        parent_id: groupForm.value.parent_id,
        kind: groupForm.value.parent_id ? groupForm.value.kind : undefined,
        window_start: showProjectWindow.value ? groupForm.value.window_start || null : undefined,
        window_end: showProjectWindow.value ? groupForm.value.window_end || null : undefined,
        description: groupForm.value.description.trim() || null,
        include_on_map: showAreaMapToggle.value ? includeOnMap : undefined,
        polygon: includeOnMap ? polygon : undefined,
      })
    }
    if (showProjectWindow.value) {
      await syncGroupPlaceCoords(saved)
    }
    await loadGroups()
    if (closeAfter) {
      closeGroupModal()
      return
    }
    editingGroup.value = groups.value.find((row) => row.id === saved.id) ?? saved
    toast.success(t('grossanlass.planung.ressorts.areaSaved'))
    await nextTick()
    await groupMapRef.value?.reloadGa?.()
    await nextTick()
    groupMapRef.value?.finishInlineDraw(editingGroup.value?.place?.id ?? null)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    isSaving.value = false
  }
}

function saveGroup() {
  return persistGroup(true)
}

function saveAreaKeepOpen() {
  return persistGroup(false)
}

async function handleDelete(group: GrossanlassGroup) {
  const ok = await confirm.confirm({
    title: t('grossanlass.planung.ressorts.deleteTitle'),
    message: t('grossanlass.planung.ressorts.deleteMessage', { name: group.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok || !departmentId.value) return
  try {
    await deleteGrossanlassGroup(departmentId.value, group.id)
    await loadGroups()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorDelete'))
  }
}

function syncAddMemberTargetGroup() {
  selectedGroup.value =
    groups.value.find((group) => group.id === addMemberTargetGroupId.value) ?? null
}

function openAddMemberDialog() {
  showMembersModal.value = false
  addMemberForm.value = { groupRole: 'member', deptRole: 'u', can_procure: false }
  addMemberTargetGroupId.value = addMemberGroupSelectItems.value[0]?.value ?? null
  syncAddMemberTargetGroup()
  showAddMemberDialog.value = true
  void loadDepartmentMembers()
}

function closeAddMemberDialog() {
  showAddMemberDialog.value = false
  addMemberTargetGroupId.value = null
  if (!showMembersModal.value) selectedGroup.value = null
}

function openMembersModal(group: GrossanlassGroup) {
  showAddMemberDialog.value = false
  selectedGroup.value = group
  showMembersModal.value = true
  addMemberForm.value = { groupRole: 'member', deptRole: 'u', can_procure: false }
  outsideSearchQuery.value = ''
  outsideUsers.value = []
  addingUserId.value = null
  loadDepartmentMembers()
}

function closeMembersModal() {
  showMembersModal.value = false
  selectedGroup.value = null
  outsideSearchQuery.value = ''
  outsideUsers.value = []
  addingUserId.value = null
}

async function refreshSelectedGroup() {
  await loadGroups()
  const updated = groups.value.find((g) => g.id === selectedGroup.value?.id)
  if (updated) selectedGroup.value = updated
}

async function handleAddMember(userId: string) {
  if (!selectedGroup.value || !userId || !departmentId.value || addingUserId.value) return
  addingUserId.value = userId
  const skipsGroupFlags = gaDeptRoleSkipsGroupFlags(addMemberForm.value.deptRole)
  try {
    await addGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, {
      user_id: userId,
      role:
        canFullyManage.value && !skipsGroupFlags && addMemberForm.value.groupRole === 'leader'
          ? 'leader'
          : 'member',
      can_procure:
        canFullyManage.value && !skipsGroupFlags && addMemberForm.value.can_procure
          ? true
          : undefined,
    })
    await applyAddMemberDeptRole(userId)
    await refreshSelectedGroup()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorAddMember'))
  } finally {
    addingUserId.value = null
  }
}

async function handleAddOutsideUser(user: AvailableUser) {
  if (!selectedGroup.value || !departmentId.value || addingUserId.value) return
  addingUserId.value = user.id
  try {
    // Bestehendes Konto: Dept + Ressort + User-Karte (wie Helfer-Anlegen, ohne neuen Account).
    await createGrossanlassHelper(departmentId.value, selectedGroup.value.id, {
      email: user.email,
      name: user.name,
    })
    if (
      canFullyManage.value &&
      !gaDeptRoleSkipsGroupFlags(addMemberForm.value.deptRole) &&
      addMemberForm.value.groupRole === 'leader'
    ) {
      await updateGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, user.id, {
        role: 'leader',
      })
    }
    if (
      canFullyManage.value &&
      !gaDeptRoleSkipsGroupFlags(addMemberForm.value.deptRole) &&
      addMemberForm.value.can_procure
    ) {
      await updateGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, user.id, {
        can_procure: true,
      })
    }
    await applyAddMemberDeptRole(user.id)
    toast.success(t('grossanlass.planung.ressorts.addOutsideSuccess', { name: user.name }))
    outsideSearchQuery.value = ''
    outsideUsers.value = []
    await refreshSelectedGroup()
    await loadDepartmentMembers()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorAddMember'))
  } finally {
    addingUserId.value = null
  }
}

async function loadOutsideUsers(query: string) {
  if (!departmentId.value || query.trim().length < 3) {
    outsideUsers.value = []
    return
  }
  isLoadingOutside.value = true
  try {
    outsideUsers.value = await getAvailableUsersForDepartment(departmentId.value, query.trim())
  } catch {
    outsideUsers.value = []
  } finally {
    isLoadingOutside.value = false
  }
}

watch(addMemberTargetGroupId, () => {
  if (showAddMemberDialog.value) syncAddMemberTargetGroup()
})

watch(
  () => addMemberForm.value.deptRole,
  (role) => {
    if (gaDeptRoleSkipsGroupFlags(role)) {
      addMemberForm.value.groupRole = 'member'
      addMemberForm.value.can_procure = false
    }
  },
)

watch(outsideSearchTrimmed, (query) => {
  if (outsideSearchTimer) clearTimeout(outsideSearchTimer)
  if (query.length < 3) {
    outsideUsers.value = []
    isLoadingOutside.value = false
    return
  }
  outsideSearchTimer = setTimeout(() => {
    void loadOutsideUsers(query)
  }, 280)
})

async function handleRoleChange(member: GroupMember, newRole: string) {
  if (!selectedGroup.value || !departmentId.value || memberSkipsGroupFlags(member.user_id)) return
  try {
    await updateGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, member.user_id, {
      role: newRole,
    })
    await loadGroups()
    const updated = groups.value.find((g) => g.id === selectedGroup.value?.id)
    if (updated) selectedGroup.value = updated
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('settings.groups.errorRoleChange'))
  }
}

async function handleCanProcureChange(member: GroupMember, canProcure: boolean) {
  if (
    !selectedGroup.value ||
    !departmentId.value ||
    !canFullyManage.value ||
    memberSkipsGroupFlags(member.user_id)
  ) {
    return
  }
  try {
    await updateGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, member.user_id, {
      can_procure: canProcure,
    })
    await loadGroups()
    const updated = groups.value.find((g) => g.id === selectedGroup.value?.id)
    if (updated) selectedGroup.value = updated
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorProcureFlag'))
  }
}

async function handlePrimaryChange(member: GroupMember, isPrimary: boolean) {
  if (!selectedGroup.value || !departmentId.value) return
  try {
    await updateGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, member.user_id, {
      is_primary: isPrimary,
    })
    await loadGroups()
    const updated = groups.value.find((g) => g.id === selectedGroup.value?.id)
    if (updated) selectedGroup.value = updated
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('settings.groups.errorRoleChange'))
  }
}

async function onHelperCreated() {
  await loadGroups()
  const updated = groups.value.find((g) => g.id === selectedGroup.value?.id)
  if (updated) selectedGroup.value = updated
  await loadDepartmentMembers()
}

async function handleRemoveMember(member: GroupMember) {
  if (!selectedGroup.value || !departmentId.value) return
  const ok = await confirm.confirm({
    title: t('settings.groups.removeMemberTitle'),
    message: t('settings.groups.removeMemberMessage', { name: member.name }),
    confirmText: t('common.remove'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await removeGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, member.user_id)
    await loadGroups()
    const updated = groups.value.find((g) => g.id === selectedGroup.value?.id)
    if (updated) selectedGroup.value = updated
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorRemoveMember'))
  }
}

watch(departmentId, () => {
  void loadGroups()
  void loadVenueAddress()
})
watch(showProjectModal, (open) => {
  if (!open) void loadGroups()
})
watch(showGroupModal, (open) => {
  if (open) void syncGroupMapPlacement()
  else groupMapRef.value?.clearInlineDraft()
})
watch(
  () => groupForm.value.kind,
  (kind) => {
    if (!showGroupModal.value) return
    if (kind === 'teilbereich') groupForm.value.include_on_map = false
    void syncGroupMapPlacement()
  },
)
watch(
  () => groupForm.value.include_on_map,
  () => {
    if (!showGroupModal.value) return
    void syncGroupMapPlacement()
  },
)
function openEditFromQuery() {
  const editId = typeof route.query.edit === 'string' ? route.query.edit : ''
  if (!editId || isLoading.value || !groups.value.length) return
  const group = groups.value.find((row) => row.id === editId)
  if (!group || !canEditGroup(group)) return
  openEditModal(group)
  void router.replace({ path: route.path, query: { ...route.query, edit: undefined } })
}

watch(
  () => [route.query.edit, isLoading.value, groups.value.length] as const,
  () => openEditFromQuery(),
)

onMounted(() => {
  void loadGroups()
  void loadVenueAddress()
})
</script>

<style scoped>
.grossanlass-ressorts {
  padding: 8px 0 24px;
}

.ressorts-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
  border-bottom: 2px solid var(--color-border, #e5e7eb);
}

.ressorts-subtabs {
  flex: 1;
  min-width: 0;
  margin-bottom: 0;
  border-bottom: 0;
}

.ressorts-subtab {
  min-height: 120px;
}

.members-filter-row {
  margin-bottom: 12px;
}

.members-filter-row .form-select {
  min-height: 40px;
  margin-bottom: 4px;
}

.members-sort-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.members-sort-actions :deep(.detail-th-sort) {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
}

.members-sort-actions :deep(.detail-th-sort-arrows) {
  display: inline-flex;
  flex-direction: column;
  line-height: 0.65;
  font-size: 8px;
  opacity: 0.35;
}

.members-sort-actions :deep(.detail-sort-chev.active) {
  opacity: 1;
  color: var(--color-primary, #059669);
}

.add-overview-existing {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
  display: grid;
  gap: 12px;
}

.stats-bar {
  display: flex;
  gap: 24px;
  padding: 14px 20px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  margin-bottom: 20px;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.stat-value {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
}

.stat-label {
  font-size: 13px;
  color: #64748b;
}

.ressorts-error {
  margin-top: 8px;
}

.table-wrapper {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: visible;
}

.groups-table {
  width: 100%;
  border-collapse: collapse;
}

.groups-table thead th {
  padding: 12px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.groups-table tbody td {
  padding: 14px 16px;
  font-size: 14px;
  color: #1e293b;
  border-bottom: 1px solid #f3f4f6;
}

.group-row:hover {
  background: #f9fafb;
}

.group-row.is-child {
  background: #fafbfc;
}

.group-row--add-root:hover {
  background: transparent;
}

.group-row--add-root td {
  border-bottom: 0;
  padding-top: 8px;
  padding-bottom: 4px;
}

.add-root-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  padding: 6px 4px;
  border: 0;
  background: transparent;
  color: #0f766e;
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.add-root-btn:hover {
  color: #115e59;
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.indent-icon {
  color: #94a3b8;
  font-size: 14px;
}

.name-stack {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.group-name {
  font-weight: 500;
}

.window-chip {
  font-size: 12px;
  color: #475569;
}

.window-hint {
  margin: 4px 0 0;
  font-size: 12px;
  color: #64748b;
}

.group-modal-map {
  margin-top: 12px;
  --ev-map-height: 320px;
}

.group-modal-map__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0 0 8px;
}

.group-modal-map__hint,
.group-modal-map__missing {
  margin: 0 0 8px;
  color: #64748b;
  font-size: 0.85rem;
}

.group-modal-map__missing {
  padding: 12px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
}

.kind-badge {
  font-size: 11px;
  color: #64748b;
}

.kind-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.cost-hint {
  margin: 0 0 12px;
  color: #64748b;
  font-size: 0.85rem;
}

.cost-flag,
.cost-set-btn {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.2;
  padding: 2px 8px;
}

.cost-flag {
  border: 0;
  background: #ecfdf5;
  color: #166534;
}

.cost-flag.is-editable {
  cursor: pointer;
}

.cost-flag:disabled {
  cursor: default;
}

.cost-set-btn {
  border: 1px solid #86efac;
  background: #fff;
  color: #166534;
  cursor: pointer;
}

.cost-set-btn:hover:not(:disabled) {
  background: #ecfdf5;
}

.cost-set-btn:disabled {
  opacity: 0.6;
  cursor: default;
}

.col-actions {
  width: 160px;
}

.action-buttons {
  display: flex;
  gap: 4px;
}

.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  background: #f3f4f6;
  border-radius: 6px;
  color: #6b7280;
  cursor: pointer;
}

.action-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

.action-btn-danger:hover {
  background: #fee2e2;
  color: #dc2626;
}

.text-muted {
  color: #9ca3af;
  font-size: 13px;
}

.members-section,
.add-member-section,
.add-helper-section {
  margin-bottom: 20px;
}

.add-helper-section {
  padding-bottom: 16px;
  border-bottom: 1px solid #e2e8f0;
}

.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  margin: 0 0 10px;
}

.subsection-title {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  margin: 16px 0 6px;
}

.add-member-role-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
  margin-bottom: 10px;
}

.add-member-procure {
  margin-left: 4px;
}

.add-member-procure-hint {
  flex: 1 1 100%;
  margin: 0;
  font-size: 0.78rem;
  color: #64748b;
}

.empty-members-cell {
  padding: 12px 10px;
  color: #94a3b8;
  font-size: 0.85rem;
}

.members-role-hint {
  margin: 0 0 10px;
  font-size: 0.78rem;
  color: #64748b;
  line-height: 1.45;
}

.member-dept-role {
  min-width: 9rem;
}

.member-modal-accordion {
  margin-top: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fafbfc;
}

.member-modal-accordion__summary {
  cursor: pointer;
  padding: 10px 12px;
  font-size: 0.9rem;
  font-weight: 600;
  color: #334155;
  list-style: none;
}

.member-modal-accordion__summary::-webkit-details-marker {
  display: none;
}

.member-modal-accordion__summary::before {
  content: '▸ ';
  color: #94a3b8;
}

.member-modal-accordion[open] > .member-modal-accordion__summary::before {
  content: '▾ ';
}

.member-modal-accordion__body {
  padding: 0 12px 12px;
  border-top: 1px solid #e2e8f0;
}

.add-member-role-label {
  font-size: 13px;
  color: #64748b;
}

.candidate-list {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.candidate-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px 10px;
  border-bottom: 1px solid #f1f5f9;
}

.candidate-row:last-child {
  border-bottom: none;
}

.candidate-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  font-size: 13px;
}

.candidate-meta strong {
  color: #1e293b;
  font-weight: 560;
}

.candidate-meta span {
  color: #64748b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.candidate-dept {
  font-size: 12px !important;
  color: #94a3b8 !important;
}

.action-btn-add {
  flex-shrink: 0;
  color: var(--color-primary, #059669);
}

.action-btn-add:hover:not(:disabled) {
  background: #d1fae5;
  color: #047857;
}

.action-btn-add:disabled {
  opacity: 0.5;
  cursor: wait;
}

.outside-dept-block {
  margin-top: 4px;
}

.outside-dept-hint,
.add-user-search-hint {
  margin: 0 0 8px;
  font-size: 12px;
  color: #94a3b8;
  line-height: 1.4;
}

.members-table {
  width: 100%;
  border-collapse: collapse;
}

.members-table th,
.members-table td {
  padding: 8px 10px;
  text-align: left;
  font-size: 13px;
  border-bottom: 1px solid #f1f5f9;
}

.member-name {
  display: flex;
  align-items: center;
  gap: 8px;
}

.flag-toggle {
  width: 28px;
  height: 28px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
  color: #94a3b8;
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
}

.flag-toggle.is-on {
  border-color: #f59e0b;
  background: #fffbeb;
  color: #d97706;
}

.flag-toggle.is-on[aria-pressed='true']:nth-of-type(1) {
  border-color: #f59e0b;
}

.flag-toggle[title] {
  color: inherit;
}

.add-member-form {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.form-select {
  padding: 8px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
}

.role-select,
.role-select-sm {
  min-width: 120px;
}

.loading-inline {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #64748b;
  font-size: 13px;
}

.spinner-sm {
  width: 16px;
  height: 16px;
  border: 2px solid #e2e8f0;
  border-top-color: var(--color-primary, #4f46e5);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.empty-members,
.no-users-hint {
  color: #64748b;
  font-size: 13px;
}

.role-readonly {
  font-size: 13px;
  color: #475569;
}
.member-overview { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.member-overview__row { min-height: 44px; }
.member-row-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}
.procure-toggle { display: inline-flex; align-items: center; gap: 6px; font-size: 0.78rem; color: #475569; cursor: pointer; white-space: nowrap; }
.procure-toggle input { margin: 0; }

@media (min-width: 768px) {
  .members-table th,
  .members-table td {
    padding: 8px 12px;
  }

  .member-email {
    max-width: 240px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .role-select-sm {
    min-width: 148px;
    max-width: 200px;
  }
}
</style>

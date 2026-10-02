{{--
    Sidebar accordion: groups start collapsed (except the one holding the current page),
    and opening a group closes the others. Hooks into Filament's own Alpine `sidebar` store,
    so the existing x-collapse animation and aria-expanded state keep working.
--}}
<script>
    (() => {
        const groupLabels = () => [...document.querySelectorAll('.fi-main-sidebar .fi-sidebar-group.fi-collapsible[data-group-label]')]
            .map((el) => el.dataset.groupLabel)
            .filter(Boolean);

        const apply = () => {
            const store = window.Alpine?.store('sidebar');
            if (! store) {
                return;
            }

            const active = document.querySelector('.fi-main-sidebar .fi-sidebar-group.fi-active[data-group-label]')?.dataset.groupLabel;
            store.collapsedGroups = groupLabels().filter((label) => label !== active);

            if (store.hzAccordion) {
                return;
            }
            store.hzAccordion = true;
            store.toggleCollapsedGroup = function (group) {
                const opening = this.collapsedGroups.includes(group);
                this.collapsedGroups = opening
                    ? groupLabels().filter((label) => label !== group)
                    : this.collapsedGroups.concat(group);
            };
        };

        document.addEventListener('alpine:initialized', apply);
        document.addEventListener('livewire:navigated', apply);
    })();
</script>

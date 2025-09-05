<x-filament-panels::page>
    <div class="fi-dashboard-page">
        <div class="grid gap-6" style="grid-template-columns: 1fr;">
            <div class="fi-widgets grid gap-6 lg:gap-8" x-data="{
                collapsibleGroupsState: {},
                
                isGroupCollapsed(group) {
                    return this.collapsibleGroupsState[group] ?? false
                },
                
                toggleGroupCollapsed(group) {
                    this.collapsibleGroupsState[group] = ! this.isGroupCollapsed(group)
                }
            }">
                @php
                    $widgets = $this->getWidgets();
                    $columns = $this->getColumns();
                @endphp
                
                <div class="grid gap-6 lg:gap-8" style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr));">
                    @foreach ($widgets as $widget)
                        @livewire($widget)
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <style>
        .fi-main {
            max-width: none !important;
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
        
        .fi-sidebar + .fi-main {
            margin-left: 0 !important;
        }
        
        @media (min-width: 1024px) {
            .fi-main {
                padding-left: 2rem !important;
                padding-right: 2rem !important;
            }
        }
        
        .fi-dashboard-page {
            width: 100%;
            max-width: 100%;
        }
        
        .fi-widgets {
            width: 100%;
        }
    </style>
</x-filament-panels::page>

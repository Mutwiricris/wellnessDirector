<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Calendar Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold">Booking Calendar</h2>
                    <p class="text-blue-100 mt-1">View and manage appointments in calendar format</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-blue-200">Current Branch</div>
                    <div class="text-lg font-semibold">
                        {{ \Filament\Facades\Filament::getTenant()?->name ?? 'All Branches' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar Legend -->
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <h3 class="text-sm font-medium text-gray-900 mb-3">Status Legend</h3>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-yellow-500 rounded"></div>
                    <span class="text-sm text-gray-600">Pending</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-blue-500 rounded"></div>
                    <span class="text-sm text-gray-600">Confirmed</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-purple-500 rounded"></div>
                    <span class="text-sm text-gray-600">In Progress</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-green-500 rounded"></div>
                    <span class="text-sm text-gray-600">Completed</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-red-500 rounded"></div>
                    <span class="text-sm text-gray-600">Cancelled</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-gray-500 rounded"></div>
                    <span class="text-sm text-gray-600">No Show</span>
                </div>
            </div>
        </div>

        <!-- Calendar Container -->
        <div class="bg-white rounded-lg shadow-sm border">
            <div id="booking-calendar" class="p-4"></div>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div id="event-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900" id="modal-title">Booking Details</h3>
                    <button id="close-modal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="modal-content" class="space-y-3">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button id="view-booking" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                        View Details
                    </button>
                    <button id="edit-booking" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded text-sm">
                        Edit
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('booking-calendar');
            const modal = document.getElementById('event-modal');
            const modalTitle = document.getElementById('modal-title');
            const modalContent = document.getElementById('modal-content');
            const closeModal = document.getElementById('close-modal');
            const viewButton = document.getElementById('view-booking');
            const editButton = document.getElementById('edit-booking');

            let currentEvent = null;

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 'auto',
                events: @json($this->getViewData()['bookings']),
                eventClick: function(info) {
                    currentEvent = info.event;
                    const props = info.event.extendedProps;
                    
                    modalTitle.textContent = props.service_name;
                    modalContent.innerHTML = `
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Reference:</span>
                                <span class="font-medium">${props.booking_reference}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Client:</span>
                                <span class="font-medium">${props.client_name}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Staff:</span>
                                <span class="font-medium">${props.staff_name}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Time:</span>
                                <span class="font-medium">${info.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} - ${info.event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Status:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="background-color: ${info.event.backgroundColor}20; color: ${info.event.backgroundColor}">
                                    ${props.status.charAt(0).toUpperCase() + props.status.slice(1)}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Payment:</span>
                                <span class="font-medium">KES ${parseFloat(props.total_amount).toLocaleString()}</span>
                            </div>
                        </div>
                    `;
                    
                    viewButton.onclick = () => window.location.href = props.view_url;
                    editButton.onclick = () => window.location.href = props.edit_url;
                    
                    modal.classList.remove('hidden');
                },
                dateClick: function(info) {
                    // Redirect to create booking with pre-filled date
                    window.location.href = '{{ $this->getViewData()["create_url"] }}?appointment_date=' + info.dateStr;
                },
                eventDidMount: function(info) {
                    // Add tooltip
                    info.el.setAttribute('title', 
                        `${info.event.extendedProps.service_name}\n` +
                        `Client: ${info.event.extendedProps.client_name}\n` +
                        `Staff: ${info.event.extendedProps.staff_name}\n` +
                        `Status: ${info.event.extendedProps.status}`
                    );
                }
            });

            calendar.render();

            // Modal close handlers
            closeModal.addEventListener('click', function() {
                modal.classList.add('hidden');
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });

            // ESC key to close modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                }
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
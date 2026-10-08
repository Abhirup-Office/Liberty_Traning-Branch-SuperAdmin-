<!-- Individual student certificate — separate from the course's global/sample certificate modal. -->
<div id="certModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" role="dialog" aria-modal="true" aria-labelledby="certModalTitle">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="font-bold text-lg" id="certModalTitle">Manage Certificate</h3>
            <button type="button" id="certCloseBtn" class="text-gray-400 hover:text-gray-600 text-xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="px-5 py-4 space-y-4 overflow-y-auto" id="certModalBody">
            <p class="text-sm text-gray-400">Loading...</p>
        </div>
    </div>
</div>

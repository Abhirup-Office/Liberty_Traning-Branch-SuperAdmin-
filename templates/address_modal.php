<!-- ============ FULL ADDRESS MODAL (filled by shared.js) ============ -->
<div id="addressModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" role="dialog" aria-modal="true" aria-labelledby="addressTitle">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="font-bold text-lg" id="addressTitle">Full Address</h3>
            <button type="button" id="addressCloseBtn" class="text-gray-400 hover:text-gray-600 text-xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="px-5 py-4 overflow-y-auto">
            <p id="addressBody" class="text-sm text-gray-800 whitespace-pre-line break-words"></p>
        </div>
    </div>
</div>

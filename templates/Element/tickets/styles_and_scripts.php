<style>
/* Only custom CSS that Bootstrap doesn't provide */

/* Main Container - Fixed height viewport */
.ticket-view-container {
    display: grid;
    grid-template-columns: 288px 1fr 288px;
    gap: 0;
    height: calc(100vh - 55px);
    max-height: calc(100vh - 55px);
    overflow: hidden;
    width: 100%;
}

/* Fixed heights for columns */
.sidebar-left,
.sidebar-right,
.main-content {
    height: calc(100dvh - 55px);
}

/* Custom scrollbars */
.sidebar-scroll::-webkit-scrollbar,
.comments-scroll::-webkit-scrollbar {
    width: 2px;
}

.sidebar-scroll::-webkit-scrollbar-track,
.comments-scroll::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.sidebar-scroll::-webkit-scrollbar-thumb,
.comments-scroll::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.sidebar-scroll::-webkit-scrollbar-thumb:hover,
.comments-scroll::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Editor tabs active state */
.editor-tab {
    background: #f8f9fa;
    color: #495057;
    font-size: 13px;
    font-weight: 500;
}

.editor-tab:hover {
    background: #e9ecef;
}

.editor-tab.active {
    background: white;
    color: #555;
    border-bottom: 2px solid #555 !important;
    margin-bottom: -1px;
}

/* Timeline styles */
.timeline {
    position: relative;
}

.timeline-item {
    position: relative;
    padding-left: 8px;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 5px;
    top: 20px;
    bottom: -12px;
    width: 1px;
    background: #dee2e6;
}

/* File upload styles */
.file-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin-bottom: 6px;
    transition: all 0.2s ease;
}

.file-item:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

.file-item-icon {
    flex-shrink: 0;
    font-size: 20px;
    width: 24px;
    text-align: center;
}

.file-item-info {
    flex-grow: 1;
    min-width: 0;
}

.file-item-name {
    font-size: 13px;
    font-weight: 500;
    color: #212529;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-item-size {
    font-size: 11px;
    color: #6c757d;
}

.file-item-remove {
    flex-shrink: 0;
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 3px;
    transition: all 0.2s ease;
    font-size: 18px;
    line-height: 1;
}

.file-item-remove:hover {
    background: #dc3545;
    color: white;
}

/* Attachment links hover effect */
.hover-bg-light:hover {
    background-color: #f8f9fa !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}
</style>

<script>
function setCommentType(type) {
    document.getElementById('comment-type').value = type;

    document.querySelectorAll('.editor-tab').forEach(tab => {
        tab.classList.remove('active');
    });

    if (type === 'public') {
        document.getElementById('tab-public').classList.add('active');
    } else {
        document.getElementById('tab-internal').classList.add('active');
    }

    const textarea = document.getElementById('comment-textarea');
    if (type === 'internal') {
        textarea.placeholder = 'Escribe una nota interna...';
    } else {
        textarea.placeholder = 'Escribe tu respuesta aquí...';
    }
}

// File management
let selectedFiles = [];

function getFileIcon(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    const iconMap = {
        // Images
        'jpg': 'bi-file-earmark-image text-success',
        'jpeg': 'bi-file-earmark-image text-success',
        'png': 'bi-file-earmark-image text-success',
        'gif': 'bi-file-earmark-image text-success',
        'bmp': 'bi-file-earmark-image text-success',
        'webp': 'bi-file-earmark-image text-success',
        // Documents
        'pdf': 'bi-file-earmark-pdf text-danger',
        'doc': 'bi-file-earmark-word text-primary',
        'docx': 'bi-file-earmark-word text-primary',
        'xls': 'bi-file-earmark-excel text-success',
        'xlsx': 'bi-file-earmark-excel text-success',
        'ppt': 'bi-file-earmark-ppt text-warning',
        'pptx': 'bi-file-earmark-ppt text-warning',
        // Text
        'txt': 'bi-file-earmark-text text-secondary',
        'csv': 'bi-file-earmark-spreadsheet text-success',
        // Archives
        'zip': 'bi-file-earmark-zip text-warning',
        'rar': 'bi-file-earmark-zip text-warning',
        '7z': 'bi-file-earmark-zip text-warning',
    };
    return iconMap[ext] || 'bi-file-earmark text-secondary';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function handleFileSelect(event) {
    const input = event.target;
    const newFiles = Array.from(input.files);

    // Add new files to the selected files array
    newFiles.forEach(file => {
        // Check if file already exists (by name and size)
        const exists = selectedFiles.some(f =>
            f.name === file.name && f.size === file.size
        );

        if (!exists) {
            selectedFiles.push(file);
        }
    });

    updateFileList();
    updateFileInput();
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateFileList();
    updateFileInput();
}

function updateFileList() {
    const fileList = document.getElementById('file-list');

    if (selectedFiles.length === 0) {
        fileList.innerHTML = '';
        return;
    }

    let html = '';
    selectedFiles.forEach((file, index) => {
        const icon = getFileIcon(file.name);
        const size = formatFileSize(file.size);

        html += `
            <div class="file-item">
                <i class="bi ${icon} file-item-icon"></i>
                <div class="file-item-info">
                    <div class="file-item-name" title="${file.name}">${file.name}</div>
                    <div class="file-item-size">${size}</div>
                </div>
                <button type="button" class="file-item-remove" onclick="removeFile(${index})" title="Eliminar archivo">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        `;
    });

    fileList.innerHTML = html;
}

function updateFileInput() {
    const input = document.getElementById('file-input');
    const dataTransfer = new DataTransfer();

    selectedFiles.forEach(file => {
        dataTransfer.items.add(file);
    });

    input.files = dataTransfer.files;
}

// Auto-scroll to bottom of comments on load
window.addEventListener('load', function() {
    const commentsArea = document.querySelector('.comments-scroll');
    if (commentsArea) {
        commentsArea.scrollTop = commentsArea.scrollHeight;
    }
});
</script>

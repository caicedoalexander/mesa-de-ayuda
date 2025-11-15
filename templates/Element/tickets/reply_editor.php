<!-- Fixed Reply Editor -->
<div class="reply-editor border-top position-relative" style="margin-top: 10px;">
    <?= $this->Form->create(null, [
        'url' => ['action' => 'addComment', $ticket->id],
        'type' => 'file',
        'id' => 'reply-form'
    ]) ?>

    <div class="d-flex border-top w-75 position-absolute start-0 bg-white" style="top: -37.5px; max-height: 37.5px;">
        <button type="button" class="editor-tab flex-grow-1 fw-normal text-decoration-none border-0 border-end rounded-0 py-2 active" onclick="setCommentType('public')" id="tab-public">
            Respuesta Pública
        </button>
        <button type="button" class="editor-tab flex-grow-1 fw-normal text-decoration-none border-0 border-end rounded-0 py-2" onclick="setCommentType('internal')" id="tab-internal">
            Nota Interna
        </button>
    </div>

    <?= $this->Form->hidden('comment_type', ['value' => 'public', 'id' => 'comment-type']) ?>

    <div class="">
        <?= $this->Form->control('comment_body', [
            'type' => 'textarea',
            'label' => false,
            'placeholder' => 'Escribe tu respuesta aquí...',
            'class' => 'form-control form-control-sm rounded-0 border-0 shadow-none p-2',
            'required' => true,
            'id' => 'comment-textarea',
            'rows' => 4,
            'style' => 'resize: none;'
        ]) ?>

        <div class="mb-2 px-3">
            <label class="btn btn-sm btn-outline-secondary rounded shadow-sm" id="file-upload-btn">
                <i class="bi bi-paperclip fw-bold fs-6"></i> Adjuntar archivos
                <?= $this->Form->file('attachments[]', [
                    'multiple' => true,
                    'id' => 'file-input',
                    'onchange' => 'handleFileSelect(event)',
                    'style' => 'display: none;',
                    'accept' => '.jpg,.jpeg,.png,.gif,.bmp,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z'
                ]) ?>
            </label>
            <div id="file-list" class="mt-2"></div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-3 align-items-center px-3 py-1 border-top">
        <div class="d-flex align-items-center gap-2">
            <label class="mb-0 fw-semibold">Estado:</label>
            <?= $this->Form->select('status', [
                'nuevo' => 'Nuevo',
                'abierto' => 'Abierto',
                'pendiente' => 'Pendiente',
                'resuelto' => 'Resuelto'
            ], [
                'value' => $ticket->status,
                'class' => 'form-select',
            ]) ?>
        </div>
        <?= $this->Form->button('Enviar Respuesta', [
            'class' => 'btn btn-success rounded-0',
            'type' => 'submit'
        ]) ?>
    </div>

    <?= $this->Form->end() ?>
</div>

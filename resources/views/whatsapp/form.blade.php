<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Mensagem via WhatsApp</title>
    <link rel="shortcut icon" href="{{ asset('images/whatsapp.svg') }}" type="image/x-icon">
    <link rel="icon" href="{{ asset('images/whatsapp.svg') }}" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --whatsapp-green: #25D366;
            --whatsapp-dark-green: #128C7E;
            --whatsapp-light-green: #DCF8C6;
        }

        body {
            background: linear-gradient(135deg, var(--whatsapp-light-green) 0%, #f8f9fa 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .card-header {
            background: linear-gradient(135deg, var(--whatsapp-green) 0%, var(--whatsapp-dark-green) 100%);
            border-radius: 15px 15px 0 0 !important;
            border: none;
            padding: 1.5rem;
        }

        .btn-whatsapp {
            background: linear-gradient(135deg, var(--whatsapp-green) 0%, var(--whatsapp-dark-green) 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 211, 102, 0.4);
            color: white;
        }

        .btn-whatsapp:disabled {
            opacity: 0.6;
            transform: none;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--whatsapp-green);
            box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
        }

        .form-check-input:checked {
            background-color: var(--whatsapp-green);
            border-color: var(--whatsapp-green);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 .25rem rgba(37, 211, 102, .25);
        }

        .char-counter {
            font-size: 0.875rem;
            color: #6c757d;
            text-align: right;
            margin-top: 5px;
        }

        .char-counter.warning {
            color: #fd7e14;
        }

        .char-counter.danger {
            color: #dc3545;
        }

        .phone-example {
            font-size: 0.875rem;
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 5px;
            padding: 8px;
            margin-top: 5px;
        }

        .loading-spinner {
            display: none;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .tab-content {
            margin-top: 1rem;
        }

        .nav-pills .nav-link {
            border-radius: 20px;
            margin-right: 10px;
        }

        .nav-pills .nav-link.active {
            background-color: var(--whatsapp-green);
        }

        .template-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .template-card:hover {
            border-color: var(--whatsapp-green);
            transform: translateY(-2px);
        }

        .template-card.selected {
            border-color: var(--whatsapp-green);
            background-color: var(--whatsapp-light-green);
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header text-white">
                    <h3 class="mb-0 d-flex align-items-center">
                        <i class="fab fa-whatsapp me-3 fs-2"></i>
                        Enviar Mensagem WhatsApp
                    </h3>
                    <p class="mb-0 mt-2 opacity-75">Envie mensagens personalizadas via WhatsApp Business API</p>
                </div>
                <div class="card-body p-4">

                    <!-- Abas para diferentes tipos de envio -->
                    <ul class="nav nav-pills nav-fill" id="sendTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="simple-tab" data-bs-toggle="pill"
                                    data-bs-target="#simple" type="button" role="tab">
                                <i class="fas fa-comment me-2"></i>Mensagem Simples
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="template-tab" data-bs-toggle="pill"
                                    data-bs-target="#template" type="button" role="tab">
                                <i class="fas fa-file-alt me-2"></i>Template
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="media-tab" data-bs-toggle="pill"
                                    data-bs-target="#media" type="button" role="tab">
                                <i class="fas fa-image me-2"></i>Mídia
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="sendTabContent">

                        <!-- Aba Mensagem Simples -->
                        <div class="tab-pane fade show active" id="simple" role="tabpanel">
                            <form id="whatsappForm" method="POST" action="{{ route('send.whatsapp') }}">
                                @csrf
                                <input type="hidden" name="message_type" value="text">

                                <!-- Números de telefone -->
                                <div class="mb-4">
                                    <label for="phoneNumber" class="form-label fw-bold">
                                        <i class="fas fa-phone me-2"></i>Números de Telefone
                                    </label>
                                    <textarea class="form-control" id="phoneNumber" name="phone_number"
                                              rows="3" required
                                              placeholder="558398765432&#10;5511987654321&#10;5521123456789"></textarea>
                                    <div class="phone-example">
                                        <strong>Formato:</strong> Código do país + DDD + número<br>
                                        <strong>Brasil:</strong> 55 + DDD + número (ex: 558398765432)<br>
                                        <strong>Múltiplos:</strong> Um número por linha ou separados por vírgula
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>

                                <!-- Toggle IA -->
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox"
                                               name="isUsedAI" id="isUsedAI">
                                        <label class="form-check-label fw-bold" for="isUsedAI">
                                            <i class="fas fa-robot me-2"></i>
                                            Processar mensagem com IA
                                        </label>
                                        <small class="text-muted d-block mt-1">
                                            A IA irá melhorar e personalizar sua mensagem
                                        </small>
                                    </div>
                                </div>

                                <!-- Mensagem -->
                                <div class="mb-4">
                                    <label for="message" class="form-label fw-bold">
                                        <i class="fas fa-comment-dots me-2"></i>Mensagem
                                    </label>
                                    <textarea class="form-control" id="message" name="message"
                                              rows="4" required maxlength="4096"
                                              placeholder="Digite sua mensagem aqui..."></textarea>
                                    <div class="char-counter">
                                        <span id="charCount">0</span> / 4096 caracteres
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>

                                <!-- Botões -->
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-whatsapp flex-grow-1">
                                            <span class="button-text">
                                                <i class="fas fa-paper-plane me-2"></i>
                                                Enviar Mensagem
                                            </span>
                                        <span class="loading-spinner">
                                                <i class="fas fa-spinner fa-spin me-2"></i>
                                                Enviando...
                                            </span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="clearForm">
                                        <i class="fas fa-trash me-2"></i>Limpar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Aba Template -->
{{--                        {{ route('send.whatsapp.template') }} --}}
                        <div class="tab-pane fade" id="template" role="tabpanel">
                            <form id="templateForm" method="POST" action="{{ route('whatsapp.send.template') }}">
                                @csrf
                                <input type="hidden" name="message_type" value="template">

                                <!-- Números de telefone -->
                                <div class="mb-4">
                                    <label for="templatePhoneNumber" class="form-label fw-bold">
                                        <i class="fas fa-phone me-2"></i>Números de Telefone
                                    </label>
                                    <textarea class="form-control" id="templatePhoneNumber"
                                              name="phone_number" rows="2" required></textarea>
                                </div>

                                <!-- Seleção de Template -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-file-alt me-2"></i>Selecionar Template
                                    </label>
                                    <div id="templateOptions">
                                        <div class="template-card" data-template="welcome">
                                            <h6 class="mb-1">Boas-vindas</h6>
                                            <p class="mb-0 text-muted">Mensagem de boas-vindas personalizada</p>
                                        </div>
                                        <div class="template-card" data-template="order_confirmation">
                                            <h6 class="mb-1">Confirmação de Pedido</h6>
                                            <p class="mb-0 text-muted">Confirma detalhes do pedido</p>
                                        </div>
                                        <div class="template-card" data-template="appointment_reminder">
                                            <h6 class="mb-1">Lembrete de Agendamento</h6>
                                            <p class="mb-0 text-muted">Lembra sobre agendamentos</p>
                                        </div>
                                    </div>
                                    <input type="hidden" name="template_name" id="selectedTemplate">
                                </div>

                                <!-- Parâmetros do Template -->
                                <div class="mb-4" id="templateParams" style="display: none;">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-cogs me-2"></i>Parâmetros do Template
                                    </label>
                                    <div id="parameterInputs"></div>
                                </div>

                                <button type="submit" class="btn btn-whatsapp w-100">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    Enviar Template
                                </button>
                            </form>
                        </div>

                        <!-- Aba Mídia -->
{{--                        {{ route('send.whatsapp.media') }} --}}
                        <div class="tab-pane fade" id="media" role="tabpanel">

                            <form id="mediaForm" method="POST" action="{{ route('whatsapp.send.media') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="message_type" value="media">

                                <!-- Números de telefone -->
                                <div class="mb-4">
                                    <label for="mediaPhoneNumber" class="form-label fw-bold">
                                        <i class="fas fa-phone me-2"></i>Números de Telefone
                                    </label>
                                    <textarea class="form-control" id="mediaPhoneNumber"
                                              name="phone_number" rows="2" required></textarea>
                                </div>

                                <!-- Upload de Mídia -->
                                <div class="mb-4">
                                    <label for="mediaFile" class="form-label fw-bold">
                                        <i class="fas fa-cloud-upload-alt me-2"></i>Arquivo de Mídia
                                    </label>
                                    <input type="file" class="form-control" id="mediaFile" name="media_file"
                                           accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                                    <div class="form-text">
                                        Tipos aceitos: Imagens (JPG, PNG, WebP), Vídeos (MP4, 3GP),
                                        Áudios (AAC, AMR, MP3, OGG), Documentos (PDF, DOC, XLS, PPT, TXT)
                                    </div>
                                </div>

                                <!-- URL alternativa -->
                                <div class="mb-4">
                                    <label for="mediaUrl" class="form-label fw-bold">
                                        <i class="fas fa-link me-2"></i>Ou URL da Mídia
                                    </label>
                                    <input type="url" class="form-control" id="mediaUrl" name="media_url"
                                           placeholder="https://exemplo.com/imagem.jpg">
                                    <div class="form-text">URL pública do arquivo de mídia</div>
                                </div>

                                <!-- Legenda -->
                                <div class="mb-4">
                                    <label for="mediaCaption" class="form-label fw-bold">
                                        <i class="fas fa-comment me-2"></i>Legenda (Opcional)
                                    </label>
                                    <textarea class="form-control" id="mediaCaption" name="caption"
                                              rows="3" maxlength="1024"
                                              placeholder="Digite uma legenda para a mídia..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-whatsapp w-100">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    Enviar Mídia
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertas -->
            @if(session('success'))
                <div class="alert alert-success mt-3 d-flex align-items-center">
                    <i class="fas fa-check-circle me-3"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning mt-3 d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-3"></i>
                    <div>{{ session('warning') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger mt-3 d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-3"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger mt-3">
                    <h6 class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Corrigir os seguintes erros:
                    </h6>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Contador de caracteres
        const messageTextarea = document.getElementById('message');
        const charCount = document.getElementById('charCount');

        if (messageTextarea && charCount) {
            messageTextarea.addEventListener('input', function() {
                const count = this.value.length;
                charCount.textContent = count;

                const counter = document.querySelector('.char-counter');
                counter.classList.remove('warning', 'danger');

                if (count > 3500) {
                    counter.classList.add('danger');
                } else if (count > 3000) {
                    counter.classList.add('warning');
                }
            });
        }

        // Validação de números de telefone
        function validatePhoneNumbers(input) {
            const phones = input.value.split(/[\n,]/).map(p => p.trim()).filter(p => p);
            const validPhones = phones.filter(phone => {
                const cleanPhone = phone.replace(/[^0-9]/g, '');
                return cleanPhone.length >= 10 && cleanPhone.length <= 15;
            });

            if (phones.length !== validPhones.length) {
                input.setCustomValidity('Alguns números de telefone estão em formato inválido');
                input.classList.add('is-invalid');
            } else {
                input.setCustomValidity('');
                input.classList.remove('is-invalid');
            }
        }

        // Aplicar validação em todos os campos de telefone
        document.querySelectorAll('[name="phone_number"]').forEach(input => {
            input.addEventListener('blur', () => validatePhoneNumbers(input));
        });

        // Seleção de templates
        document.querySelectorAll('.template-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove seleção anterior
                document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));

                // Adiciona seleção atual
                this.classList.add('selected');

                const template = this.dataset.template;
                document.getElementById('selectedTemplate').value = template;

                // Mostra parâmetros se necessário
                showTemplateParameters(template);
            });
        });

        // Mostrar parâmetros do template
        function showTemplateParameters(template) {
            const paramsDiv = document.getElementById('templateParams');
            const inputsDiv = document.getElementById('parameterInputs');

            // Limpa inputs anteriores
            inputsDiv.innerHTML = '';

            const templates = {
                'welcome': ['Nome do cliente'],
                'order_confirmation': ['Número do pedido', 'Valor total', 'Data de entrega'],
                'appointment_reminder': ['Nome do cliente', 'Data do agendamento']
            };

            if (templates[template]) {
                templates[template].forEach((param, index) => {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control mb-2';
                    input.placeholder = param;
                    input.name = `template_params[${index}]`;
                    input.required = true;

                    const label = document.createElement('label');
                    label.textContent = param;
                    label.className = 'form-label';

                    inputsDiv.appendChild(label);
                    inputsDiv.appendChild(input);
                });

                paramsDiv.style.display = 'block';
            } else {
                paramsDiv.style.display = 'none';
            }
        }

        // Loading state nos formulários
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                const buttonText = submitBtn.querySelector('.button-text');
                const loadingSpinner = submitBtn.querySelector('.loading-spinner');

                if (buttonText && loadingSpinner) {
                    buttonText.style.display = 'none';
                    loadingSpinner.style.display = 'inline';
                    submitBtn.disabled = true;
                }
            });
        });

        // Limpar formulário
        document.getElementById('clearForm')?.addEventListener('click', function() {
            const form = document.getElementById('whatsappForm');
            form.reset();
            charCount.textContent = '0';
            document.querySelector('.char-counter').classList.remove('warning', 'danger');
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        });

        // Validação de arquivo de mídia
        document.getElementById('mediaFile')?.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 16 * 1024 * 1024; // 16MB
                if (file.size > maxSize) {
                    alert('Arquivo muito grande. Tamanho máximo: 16MB');
                    this.value = '';
                }
            }
        });

        // Alternar entre arquivo e URL
        const mediaFile = document.getElementById('mediaFile');
        const mediaUrl = document.getElementById('mediaUrl');

        if (mediaFile && mediaUrl) {
            mediaFile.addEventListener('change', function() {
                if (this.files.length > 0) {
                    mediaUrl.disabled = true;
                    mediaUrl.value = '';
                } else {
                    mediaUrl.disabled = false;
                }
            });

            mediaUrl.addEventListener('input', function() {
                if (this.value.trim()) {
                    mediaFile.disabled = true;
                    mediaFile.value = '';
                } else {
                    mediaFile.disabled = false;
                }
            });
        }
    });
</script>
</body>
</html>

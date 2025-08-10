<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Mensagem via WhatsApp</title>
    <link rel="shortcut icon" href="{{ asset('images/whatsapp.svg') }}" type="image/x-icon">
    <link rel="icon"          href="{{ asset('images/whatsapp.svg') }}" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-check-input:checked {
            background-color: #198754;   /* cor success do Bootstrap */
            border-color:     #198754;
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 .25rem rgba(25, 135, 84, .25);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0">Enviar Mensagem para WhatsApp</h3>
                    </div>
                    <div class="card-body">
                        <form id="whatsappForm" method="POST" action="{{ route('send.whatsapp') }}">
                            @csrf
                                <!-- Number example: 558398530445 -->
                                <div class="mb-3">
                                <label for="phoneNumber" class="form-label">Número de Telefone</label>
                                <textarea class="form-control" id="phoneNumber" name="phone_number" rows="3" 
                                          placeholder="55839xxxxxxxx, 55839yyyyyyyy" required></textarea>
                                <div class="form-text">Insira o número completo com código do país (Ex: 55 para Brasil)</div>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input text-success" type="checkbox" role="switch" name="isUsedAI" id="isUsedAI">
                                <label class="form-check-label" for="isUsedAI">Sem IA / Com IA </label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Mensagem</label>
                                <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success">Enviar Mensagem</button>
                        </form>
                    </div>
                </div>
                
                @if(session('success'))
                <div class="alert alert-success mt-3">
                    {{ session('success') }}
                </div>
                @endif
                
                @if(session('error'))
                <div class="alert alert-danger mt-3">
                    {{ session('error') }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

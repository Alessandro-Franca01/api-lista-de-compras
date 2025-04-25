<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

# API de Lista de Compras

Esta é uma API desenvolvida em Laravel para gerenciar listas de compras e permitir a interação entre usuários através de um sistema de amizade.

## Funcionalidades

- **Gerenciamento de Usuários**: Criação, atualização e exclusão de usuários.
- **Listas de Compras**: Criação, leitura, atualização e exclusão de listas de compras.
- **Compartilhamento de Listas**: Usuários podem compartilhar suas listas de compras com outros usuários.
- **Sistema de Amizade**: Usuários podem enviar, aceitar e rejeitar solicitações de amizade.

## Tecnologias Utilizadas

- [Laravel](https://laravel.com) - Framework PHP para desenvolvimento de aplicações web.
- [Sanctum](https://laravel.com/docs/sanctum) - Para autenticação de API.
- [Eloquent ORM](https://laravel.com/docs/eloquent) - Para interações com o banco de dados.

## Instalação

1. Clone o repositório:
   ```bash
   git clone https://github.com/Alessandro-Franca01/api-lista-de-compras.git
   cd seu_repositorio
   ```

2. Instale as dependências do Composer:
   ```bash
   composer install
   ```

3. Crie um arquivo `.env` a partir do arquivo `.env.example`:
   ```bash
   cp .env.example .env
   ```

4. Gere a chave da aplicação:
   ```bash
   php artisan key:generate
   ```

5. Execute as migrações para criar as tabelas no banco de dados:
   ```bash
   php artisan migrate
   ```

6. Inicie o servidor de desenvolvimento:
   ```bash
   php artisan serve
   ```

## Endpoints

### Autenticação

- **Login**: `POST /api/login`
- **Logout**: `POST /api/logout`

### Usuários

- **Listar Usuários**: `GET /api/users`
- **Criar Usuário**: `POST /api/users`
- **Mostrar Usuário**: `GET /api/users/{id}`
- **Atualizar Usuário**: `PUT /api/users/{id}`
- **Deletar Usuário**: `DELETE /api/users/{id}`

### Listas de Compras

- **Listar Listas**: `GET /api/lists`
- **Criar Lista**: `POST /api/lists`
- **Mostrar Lista**: `GET /api/lists/{id}`
- **Atualizar Lista**: `PUT /api/lists/{id}`
- **Deletar Lista**: `DELETE /api/lists/{id}`
- **Compartilhar Lista**: `POST /api/lists/{id}/share`

### Amizades

- **Enviar Solicitação de Amizade**: `POST /api/friends/{friend}`
- **Aceitar Solicitação de Amizade**: `PUT /api/friends/{friend}/accept`
- **Rejeitar Solicitação de Amizade**: `PUT /api/friends/{friend}/reject`
- **Remover Amizade**: `DELETE /api/friends/{friend}`
- **Listar Amigos**: `GET /api/friends`

## Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para abrir um problema ou enviar um pull request.

## Licença

Este projeto é licenciado sob a Licença MIT. Veja o arquivo [MIT LICENSE](https://opensource.org/license/MIT) para mais detalhes.

## Contato

Para dúvidas ou sugestões, entre em contato com [alessandro0564@yahoo.com.br](mailto:alessandro0564@yahoo.com.br).

# 🚀 SISCONDI - Guia de Início Rápido

## ⚠️ IMPORTANTE - EXECUTE ESTES COMANDOS AGORA

### 1️⃣ Instalar Dependências do Backend

```bash
composer install
```

### 2️⃣ Configurar Ambiente

```bash
# Copiar arquivo de exemplo (se ainda não tiver)
cp .env.example .env

# Gerar chave da aplicação
php artisan key:generate
```

### 3️⃣ Configurar Banco de Dados

Edite o arquivo `.env` e configure:

```env
APP_NAME=SISCONDI

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=siscondi
DB_USERNAME=root
DB_PASSWORD=secret
```

### 4️⃣ Criar Banco de Dados

```bash
# MySQL
mysql -u root -p
CREATE DATABASE siscondi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

### 5️⃣ Executar Migrations e Seeders

```bash
php artisan migrate --seed
```

**Isso irá criar:**
- ✅ Todas as tabelas do sistema
- ✅ 5 perfis de acesso (Admin, Requerente, Validador, Concedente, Pagador)
- ✅ Usuário admin padrão
- ✅ Secretaria principal

### 6️⃣ Iniciar Servidor

```bash
php artisan serve
```

O backend estará rodando em: `http://localhost:8000`

---

## 🔐 Credenciais de Acesso Padrão

Após executar os seeders, use estas credenciais para fazer login:

**Usuário Admin:**
- Email: `admin@siscondi.gov.br`
- Senha: `password`

---

## 🧪 Testar API

### Fazer Login
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@siscondi.gov.br",
    "password": "password"
  }'
```

### Listar Legislações
```bash
curl -X GET http://localhost:8000/api/legislations \
  -H "Authorization: Bearer {seu_token}"
```

---

## 📱 Frontend (Opcional - Desenvolvimento Futuro)

```bash
cd frontend
npm install
npm run dev
```

O frontend estará rodando em: `http://localhost:5173`

---

## ✅ Checklist de Verificação

Após executar os comandos acima, verifique:

- [ ] Composer instalou todas as dependências
- [ ] Arquivo .env está configurado
- [ ] Banco de dados foi criado
- [ ] Migrations executaram sem erros
- [ ] Seeders criaram os perfis e usuário admin
- [ ] Servidor Laravel está rodando
- [ ] API responde em http://localhost:8000/api
- [ ] Login funciona com as credenciais padrão

---

## 🐛 Problemas Comuns

### Erro: "Class not found"
```bash
composer dump-autoload
```

### Erro: "SQLSTATE[HY000] [1049] Unknown database"
```bash
# Certifique-se de criar o banco antes:
mysql -u root -p
CREATE DATABASE siscondi;
exit;
```

### Erro: "No application encryption key"
```bash
php artisan key:generate
```

### Erro de Permissões (Linux/Mac)
```bash
chmod -R 775 storage bootstrap/cache
```

---

## 📚 Próximos Passos

1. ✅ **Backend está pronto!**
2. 📖 Leia `README.md` para entender o sistema completo
3. 🎨 Consulte `FRONTEND_STRUCTURE.md` para desenvolver o frontend
4. 📊 Veja `TRANSFORMATION_SUMMARY.md` para detalhes da arquitetura

---

## 🎯 Endpoints Principais da API

### Autenticação
- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `GET /api/user` - Usuário autenticado

### Dashboard
- `GET /api/dashboard` - Estatísticas gerais

### Legislações (Cargos)
- `GET /api/legislations` - Listar
- `POST /api/legislations` - Criar
- `GET /api/legislations/{id}` - Detalhes
- `PUT /api/legislations/{id}` - Atualizar
- `DELETE /api/legislations/{id}` - Deletar

### Servidores
- `GET /api/servants` - Listar
- `POST /api/servants` - Criar
- `GET /api/servants/{id}` - Detalhes
- `PUT /api/servants/{id}` - Atualizar
- `DELETE /api/servants/{id}` - Deletar

### Solicitações de Diárias
- `GET /api/daily-requests` - Listar
- `POST /api/daily-requests` - Criar
- `GET /api/daily-requests/{id}` - Detalhes
- `PUT /api/daily-requests/{id}` - Atualizar
- `DELETE /api/daily-requests/{id}` - Deletar
- `POST /api/daily-requests/{id}/validate` - Validar (Secretário)
- `POST /api/daily-requests/{id}/authorize` - Autorizar (Prefeito)
- `POST /api/daily-requests/{id}/pay` - Pagar (Tesoureiro)
- `POST /api/daily-requests/{id}/cancel` - Cancelar

### Secretarias
- `GET /api/branches` - Listar

---

## 💡 Dica

Use ferramentas como **Postman** ou **Insomnia** para testar a API de forma mais fácil!

Importe a collection que está em: `Adonay System API.postman_collection.json` (você precisará atualizar os endpoints para os novos do SISCONDI)

---

**🎉 Pronto! O SISCONDI está funcionando!**

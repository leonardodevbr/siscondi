# Adonai PDV – Frontend

Vue 3 + Vite + Vue Router.

## Deploy em produção (ex.: servidor compartilhado)

1. **Variáveis de ambiente**
   - Crie `frontend/.env.production` (veja `.env.production.example`).
   - Defina `VITE_API_URL` com a URL base da API em produção (ex.: `https://seudominio.com.br/api`).

2. **Build**
   ```bash
   cd frontend
   npm ci
   npm run build
   ```

3. **Publicar**
   - Aponte o **document root** do site para a pasta `dist` (ou publique o **conteúdo** de `dist` na pasta do document root).
   - A `dist` já inclui `index.html`, `.htaccess` (para Apache) e os assets.

4. **Recarregar página (SPA)**
   - O `public/.htaccess` faz o Apache redirecionar todas as rotas para `index.html`, então recarregar em `/pos`, `/sales`, etc. deixa de quebrar.
   - Se o servidor for **nginx**, use algo como:
     ```nginx
     location / {
       try_files $uri $uri/ /index.html;
     }
     ```
   - Se **não puder** configurar o servidor, dá para usar rotas com **hash** (`/#/pos`): troque no router `createWebHistory()` por `createWebHashHistory()` e faça novo build. Aí não precisa de `.htaccess`/nginx.

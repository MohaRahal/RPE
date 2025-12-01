# Guia Completo de Migração Laravel 6 → Laravel 12

Este documento detalha **todos os passos** executados para migrar o projeto RPE de Laravel 6 para Laravel 12. Use como referência para futuras migrações.

---

## 📋 Pré-requisitos

### Requisitos de Sistema
- PHP 8.2 ou superior
- Composer 2.x
- MySQL/Oracle Database
- Node.js e NPM (para assets)

### Backup
```bash
# Fazer backup completo do projeto Laravel 6
cp -r /caminho/projeto/antigo /caminho/backup/projeto_laravel6_backup
```

---

## 🚀 Parte 1: Criação do Novo Projeto

### 1.1 Criar Projeto Laravel 12
```bash
composer create-project laravel/laravel RPE_v12
cd RPE_v12
```

### 1.2 Configurar `.env`
Copie as configurações do projeto antigo e ajuste:

```env
APP_NAME=RPE
APP_ENV=local
APP_KEY=base64:... # Gerar novo: php artisan key:generate
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Database (ajustar conforme seu banco)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rpe
DB_USERNAME=root
DB_PASSWORD=

# Keycloak
KEYCLOAK_BASE_URL=https://seu-keycloak.com
KEYCLOAK_REALM=seu-realm
KEYCLOAK_CLIENT_ID=seu-client
KEYCLOAK_CLIENT_SECRET=seu-secret

# FakeLogin (para testes)
APP_FAKE_LOGIN=true
FAKE_LOGIN_USER_ID=480
```

---

## 📦 Parte 2: Instalação de Dependências

### 2.1 Instalar Pacotes Principais
```bash
composer require barryvdh/laravel-dompdf
composer require guzzlehttp/guzzle
composer require spatie/laravel-permission
composer require vizir/laravel-keycloak-web-guard
composer require yajra/laravel-datatables-oracle
```

### 2.2 Publicar Configurações
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Vizir\KeycloakWebGuard\KeycloakWebGuardServiceProvider"
```

---

## 🔧 Parte 3: Migração de Código

### 3.1 Models

**Mudanças Necessárias:**
1. Namespace: `App\` → `App\Models\`
2. Imports de relacionamentos devem usar `App\Models\`

**Exemplo de Migração:**

**Antes (Laravel 6):**
```php
<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Atividade_de_Risco extends Model
{
    protected $table = 'atividade_de__riscos';
    
    public function naturezas()
    {
        return $this->hasMany('App\Natureza_de_atividade', 'atividade_de__risco_id');
    }
}
```

**Depois (Laravel 12):**
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Atividade_de_Risco extends Model
{
    protected $table = 'atividade_de__riscos';
    
    public function naturezas()
    {
        return $this->hasMany(Natureza_de_atividade::class, 'atividade_de__risco_id');
    }
}
```

**Arquivos a Migrar:**
- `app/User.php` → `app/Models/User.php`
- Todos os models em `app/*.php` → `app/Models/*.php`

**User.php Específico:**
```php
<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'nome', 'username', 'Aprovador'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

### 3.2 Controllers

**Mudanças Necessárias:**
1. Imports: `use App\ModelName;` → `use App\Models\ModelName;`
2. Facades: Usar imports corretos

**Exemplo:**

**Antes:**
```php
<?php
namespace App\Http\Controllers;

use App\User;
use App\Atividade_de_Risco;
use Auth;
use DB;
```

**Depois:**
```php
<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Atividade_de_Risco;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
```

**Copiar Controllers:**
```bash
cp ../RPE/app/Http/Controllers/DashboardController.php app/Http/Controllers/
cp ../RPE/app/Http/Controllers/Usercontroller.php app/Http/Controllers/
# Repetir para todos os controllers
```

### 3.3 Routes

**`routes/web.php`:**
```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Usercontroller;

// Conditional authentication based on environment
if (env('APP_FAKE_LOGIN', false)) {
    Route::get('/', function () {
        return redirect('/dashboard');
    });
} else {
    Route::group(['middleware' => 'keycloak-web'], function () {
        Route::get('/', function () {
            return redirect('/dashboard');
        });
    });
}

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/registro_atividade', [DashboardController::class, 'registro_atividade']);
Route::post('/registro_atividade/registrar', [DashboardController::class, 'registrar_atividade'])->name('registro.atividade');
// ... adicionar todas as rotas
```

**`routes/console.php`:**
```php
<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Spatie\Permission\Models\Role;

Artisan::command('assign-role {userId} {roleName}', function ($userId, $roleName) {
    $user = User::find($userId);
    $role = Role::where('name', $roleName)->first();
    
    if ($user && $role) {
        $user->assignRole($role);
        $this->info("Role '{$roleName}' assigned to user {$userId}");
    } else {
        $this->error('User or role not found');
    }
});
```

### 3.4 Middleware

**Criar `app/Http/Middleware/FakeLogin.php`:**
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class FakeLogin
{
    public function handle(Request $request, Closure $next)
    {
        if (env('APP_FAKE_LOGIN', false) && !Auth::check()) {
            $userId = 480; // Hardcoded ou usar env('FAKE_LOGIN_USER_ID')
            $user = User::find($userId);
            
            if ($user) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
```

**Registrar em `bootstrap/app.php`:**
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\FakeLogin::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

### 3.5 Jobs

**Mudanças:**
- Namespace correto
- Imports de Models atualizados

**Exemplo `app/Jobs/EnviarEmailAprovacao.php`:**
```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Mail\mailprime;
use Illuminate\Support\Facades\Mail;

class EnviarEmailAprovacao implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $details;
    public $atividade;
    // ... resto do código
}
```

### 3.6 Mailables

**Exemplo `app/Mail/mailprime.php`:**
```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class mailprime extends Mailable
{
    use Queueable, SerializesModels;

    public $atividade;
    public $hash_aprv;
    // ... propriedades

    public function __construct($atividade, $hash_aprv, $hash_rprv, $user_participantes, $ip)
    {
        $this->atividade = $atividade;
        // ... atribuições
    }

    public function build()
    {
        return $this->subject('Aprovação de Atividade')
                    ->view('emails.aprovacao');
    }
}
```

### 3.7 Notifications

**Exemplo `app/Notifications/emails.php`:**
```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class emails extends Notification
{
    // ... código da notificação
}
```

### 3.8 Views

**Copiar todas as views:**
```bash
cp -r ../RPE/resources/views/* resources/views/
```

**Ajustes Necessários em Blade:**
- Verificar se há `@php` blocks que precisam de ajustes
- Atualizar referências a assets se necessário

### 3.9 Migrations e Seeders

**Copiar:**
```bash
cp -r ../RPE/database/migrations/* database/migrations/
cp -r ../RPE/database/seeders/* database/seeders/
```

**Atualizar Seeders:**

**Antes:**
```php
<?php
use Illuminate\Database\Seeder;
use App\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        // ...
    }
}
```

**Depois:**
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ...
    }
}
```

**`database/seeders/DatabaseSeeder.php`:**
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            NaturezaSeeder::class,
            AreaSeeder::class,
            LocalSeeder::class,
        ]);
    }
}
```

### 3.10 Assets Públicos

**Copiar:**
```bash
cp -r ../RPE/public/css public/
cp -r ../RPE/public/js public/
cp -r ../RPE/public/imgs public/
cp -r ../RPE/public/bootstrap public/
cp -r ../RPE/public/DataTables public/
cp -r ../RPE/public/jquary public/
cp -r ../RPE/public/jquaryUI public/
```

---

## ⚙️ Parte 4: Configurações

### 4.1 Keycloak (`config/keycloak-web.php`)

**Adicionar opção para desabilitar SSL:**
```php
'guzzle_options' => [
    'verify' => false, // Desabilita verificação SSL para certificados auto-assinados
],
```

### 4.2 Database (`config/database.php`)

Ajustar conforme necessário para Oracle ou MySQL.

---

## 🔄 Parte 5: Executar Migrações

```bash
# Rodar migrations
php artisan migrate

# Rodar seeders
php artisan db:seed

# Ou tudo junto
php artisan migrate:fresh --seed
```

---

## 🎨 Parte 6: Melhorias Específicas (Select2)

### 6.1 Adicionar Select2 ao Blade

**Em `resources/views/registro_atividades.blade.php`:**

```html
<!-- No <head> -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Substituir inputs antigos por selects -->
<select class="form-control select2-multi" id="input_natureza" name="tags_input[]" multiple="multiple" style="width: 100%">
    @foreach ($naturezas as $natureza)
        <option value="{{$natureza->id}}" {{ in_array($natureza->id, old('tags_input', [])) ? 'selected' : '' }}>
            {{$natureza->nome_natureza}}
        </option>
    @endforeach
</select>

<!-- Inicializar Select2 antes de </body> -->
<script>
    $(document).ready(function() {
        $('.select2-multi').select2({
            placeholder: "Selecione uma ou mais opções",
            allowClear: true
        });
    });
</script>
```

### 6.2 Atualizar Controller para Arrays

**Em `DashboardController.php`, método `registrar_atividade`:**

```php
// Receber arrays diretamente
$natureza_array = $request->tags_input ?? [];
$area_array = $request->tags_area ?? [];
$local_array = $request->tags_local ?? [];
$participantes_array = $request->tags_participante ?? [];

// Garantir que são arrays
if (!is_array($natureza_array)) $natureza_array = [$natureza_array];
if (!is_array($area_array)) $area_array = [$area_array];
if (!is_array($local_array)) $local_array = [$local_array];
if (!is_array($participantes_array)) $participantes_array = [$participantes_array];

// Validar IDs
$count_natureza = Natureza_atividade::whereIn('id', $natureza_array)->count();
if ($count_natureza != count(array_unique($natureza_array))) {
    return redirect('/registro_atividade')->withInput($request->input())
        ->with('message','ID(s) de Natureza(s) incorreto(s)!');
}

// Buscar nomes para colunas legadas
$locais_models = Local::whereIn('id', $local_array)->get();
$locais_names_str = $locais_models->pluck('nome_local')->implode(',');

$participantes_models = User::whereIn('id', $participantes_array)->get();
$participantes_names_str = $participantes_models->pluck('name')->implode(',');

// Salvar atividade
$atividade->tags_natureza = implode(',', $natureza_array);
$atividade->tags_area = implode(',', $area_array);
$atividade->tags_locais = $locais_names_str;
$atividade->tags_participantes = $participantes_names_str;
$atividade->save();

// Salvar relacionamentos nas tabelas pivô
foreach ($natureza_array as $natureza_id) {
    $natureza_atv = new Natureza_de_atividade;
    $natureza_atv->natureza_de_atividade_id = $natureza_id;
    $natureza_atv->atividade_de__risco_id = $atividade->id;
    $natureza_atv->save();
}

// Repetir para area, local, participantes
```

### 6.3 Atualizar Validação do Formulário

**Remover validação antiga baseada em hidden inputs:**

```javascript
// REMOVER:
$('#form_atv').submit(function() {
    if ($.trim($("#hidden_naturezas").val()) === "") {
        alert('Campos precisam ser preenchidos!');
        return false;
    }
});

// ADICIONAR:
$('#form_atv').submit(function() {
    if ($("#input_natureza").val().length === 0 || 
        $("#input_area").val().length === 0 || 
        $("#input_participantes").val().length === 0 || 
        $("#input_local").val().length === 0) {
        alert('Todos os campos precisam ser preenchidos!');
        return false;
    }
});
```

### 6.4 Deletar Arquivos Obsoletos

```bash
rm public/js/prime.js
```

---

## ✅ Parte 7: Testes

### 7.1 Testar Autenticação
```bash
php artisan serve
```
Acessar `http://127.0.0.1:8000` e verificar se FakeLogin funciona.

### 7.2 Testar Dashboard
- Verificar se exibe conteúdo baseado em roles
- Verificar se não há erros de variáveis indefinidas

### 7.3 Testar Formulário
1. Acessar `/registro_atividade`
2. Preencher todos os campos usando Select2
3. Submeter formulário
4. Verificar se atividade foi criada no banco
5. Verificar se relacionamentos foram salvos

### 7.4 Comandos Úteis
```bash
# Atribuir role a usuário
php artisan assign-role 480 Admin

# Limpar cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Ver logs
tail -f storage/logs/laravel.log
```

---

## 🐛 Problemas Comuns e Soluções

### Erro: "Class 'App\User' not found"
**Solução:** Atualizar imports para `App\Models\User`

### Erro: "explode() expects string, array given"
**Solução:** Remover `explode()` e trabalhar diretamente com arrays do Select2

### Erro: "htmlspecialchars() expects string, array given"
**Solução:** Remover scripts que tentam echo de arrays. Usar `old()` corretamente:
```php
{{ in_array($item->id, old('field', [])) ? 'selected' : '' }}
```

### Erro: "in_array() expects array, string given"
**Solução:** Fazer cast para array:
```php
{{ in_array($item->id, (array) old('field', [])) ? 'selected' : '' }}
```

### Erro: "Undefined variable $users"
**Solução:** Adicionar `$users = User::all();` antes do uso

### Erro SSL Keycloak
**Solução:** Adicionar `'verify' => false` em `config/keycloak-web.php`

---

## 📝 Checklist Final

- [ ] Projeto Laravel 12 criado
- [ ] `.env` configurado
- [ ] Dependências instaladas
- [ ] Models migrados e namespaces atualizados
- [ ] Controllers migrados e imports corrigidos
- [ ] Routes configuradas
- [ ] Middleware FakeLogin criado e registrado
- [ ] Jobs, Mailables, Notifications migrados
- [ ] Views copiadas
- [ ] Migrations e Seeders atualizados
- [ ] Assets públicos copiados
- [ ] Keycloak configurado
- [ ] Migrations executadas
- [ ] Select2 implementado (se aplicável)
- [ ] Controller atualizado para arrays
- [ ] Validação de formulário atualizada
- [ ] Arquivos obsoletos deletados
- [ ] Testes realizados
- [ ] Sistema funcionando 100%

---

## 📚 Referências

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Laravel Upgrade Guide](https://laravel.com/docs/12.x/upgrade)
- [Select2 Documentation](https://select2.org/)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)

---

**Data da Migração:** 2025-12-01  
**Versão Original:** Laravel 6  
**Versão Final:** Laravel 12  
**Status:** ✅ Completo e Funcional

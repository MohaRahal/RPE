# Documentação de Instalação - RPE em Máquina Virtual (VM) Ubuntu 24.04.2

## Visão Geral

Este documento descreve os passos necessários para realizar a instalação da aplicação **RPE** em uma máquina virtual, incluindo os pré-requisitos, preparação do ambiente, processo de instalação e configurações pós-instalação.


<hr>

### Sitema Utilizado na Máquina Virtual
- **Sistema Operacional:** 
 Ubuntu 20.04.2 ^
- **CPU:** 2 NUCLEOS
- **Memória RAM:** 8GB
- **Disco:** 20GB
- **Rede:** Acesso a rede

### Dependências
- [ ] PHP 
- [ ] mySQL 
- [ ] Composer 
- [ ] Laravel 
- [ ] Apache 
- [ ] Supervisor



## Preparação do Ambiente

1. **Atualizar o sistema:**
   ```php
   ssh estagio@192.168.137.25
   estagio //senha da vm

   sudo apt update && sudo apt upgrade -y
   ```

## Instalando as Dependências
<p>Esteja em modo root para realizar todos os comandos</p><br>

1. **Adicionando o Repositório**
    <br><small>É necessario adicionar esse repositório , pois nao é possivel instalar a versao necessária do php que o sistema utiliza</small>

   ```php
    sudo apt update
    sudo apt install software-properties-common
    sudo add-apt-repository ppa:ondrej/php
    //pode demorar um pouco
    sudo apt update
    ```
    **Instalando o PHP**
   ```php
   sudo apt install php7.2 php7.2-gd php7.2-cli php7.2-mbstring php7.2-xml php7.2-bcmath php7.2-curl php7.2-mysql php7.2-zip unzip curl git -y
   sudo ln -s /usr/bin/php /usr/local/bin/php
   ```

2. **Composer, Artisan & Laravel**
   ```php
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    sudo mv composer.phar /usr/local/bin/composer
    composer --version
    // composer é necessario para instalar pacotes para a aplicação
   ```

2. **Artisan & Laravel**
   ```php
    composer create-project --prefer-dist laravel/laravel:^6.0 nome-do-projeto
    cd nome-do-projeto
    //crie um projeto teste para apenas instalar o laravel ma versao correta e instalar o artisan
    php artisan --version
    // artisan ajuda a criar arquivos pre configurados no laravel por meio de linha de comando e rodar migrations e tudo mais
   ```

3. **Apache** (Caso nao tenha)
   ```php
    sudo apt install apache2 -y

    sudo systemctl status apache2
    // se aparecer enable ou running é porque esta rodando

    systemctl enable apache2
    //para inicializar junto com a vm
    ```

4. **mySQL**
   ```php
    // instalação do banco de dados 
    sudo apt install mysql-server -y
    sudo systemctl status mysql-server

    dpkg -l | grep mysql-server
    // tem que mostrar 3 mysql-server,mysql-server8.0,mysql-server-core8.0
    ```


5. **Supervisor**
   ```php
    sudo apt install supervisor -y
    sudo systemctl status supervisor
    sudo systemctl enable --now supervisor
    //supervisor serve para deixar comandos php rodando em segundo plano como queues para enviar emails, processamento de imagens e afins
    ```


5. **Configurando Supervisor**
   ```php
    nano /etc/supervisor/conf.d/laravel-worker.conf
    // copie e coloque no arquivo o código abaixo
    [program:laravel-worker]
    command=php /var/www/html/RPE/artisan queue:listen
    process_name=%(program_name)s_%(process_num)02d
    numprocs=8
    priority=999
    autostart=true
    autorestart=true
    startsecs=1
    startretries=3
    user=root
    redirect_stderr=true
    stdout_logfile=/var/www/html/RPE/storage/logs/worker.log
    ```
    ## Movimentação do Projeto entre Máquinas

    ### Mover o Projeto da Máquina local para a VM

    #### Compactar o projeto 
    ```php
        tar -czf <nome_para_o_arquivo_compactado> /caminho/do/projeto
    ```

    #### Enviar via Scp para a VM 
    ```php
        // comando na maquina aonde se econtra o arquivo
        scp laravel6-rpe.tar.gz estagio@<IP Maquina de Destino>:/tmp/
    ```

    #### Descompactar o projeto e movendo para o local correto
    ```php
        // maquina de destino 
        cd /var/www/html
        sudo tar -xzf /tmp/laravel6-rpe.tar.gz
        mv /var/www/html/home/estagio/Documentos/rpe/RPE  /var/www/html/
    ```
    **Dando restart Supervisor**
   ```php
    sudo supervisorctl -c /etc/supervisor/supervisord.conf reread
    sudo supervisorctl -c /etc/supervisor/supervisord.conf update
    sudo supervisorctl -c /etc/supervisor/supervisord.conf start laravel-worker:*
    sudo supervisorctl -c /etc/supervisor/supervisord.conf status
    ```


## Dump do Banco de dados

1. **Conectar via SSH na maquina que esta rodando a Aplicação**
   ```php
    ssh estagio@172.17.67.99
    estagio@2025 //senha
   ```
2. **Criar o Backup do banco** 
    ```php
        mysqldump -u root -p RPE > /tmp/<nome-do-backup>.sql
    ```

3. **Confirmar que o arquivo de backup foi criado**
    ```php
        ls -lh /tmp/<nome-do-backup>.sql
    ```


    Após estes passos o arquivo de backup do banco de dados tera sido criado, para transferir esse arquivo para o notebook.
    #### Para conseguir pegar o arquivo de backup é necessario realizar os seguintes passos:
    - [WinSCP](https://winscp.net) : Basta abrir o app , ir em nova aba colocar o ip da maquina aonde esta rodando o serviço(172.17.67.99) , e inserir suas credencias. Após isso, basta pegar um pen-drive para passar para ele , e assim poder passar o arquivo para o notebook.
    <br>

    Após isso voltando para o notebook basta enviar o arquivo para a VM
    ```php
        scp <Nome-do-backup> estagio@192.168.137.25:/tmp/<local-aonde-ficara-o-backup>
    ```
    <p>Para facilitar mude o local de destino para um mais facil , voce ira usar apenas uma vez ess arquivo</p>

    ### Terminal do mySQL
    ```php
        mysql -u root -p
    ```

    #### Criação do banco
    ```php
        CREATE DATABASE RPE;
    ```

    #### Criação do banco
    ```php
        mysql -u root -p RPE < /caminho/aonde/se/econtra/o/arquivo/de/backup
    ```

    #### Ver o Banco de Dados Importado
    ```php
        USE RPE
        SHOW DATABASES;
    ```
    #### Ver as Tabelas Importadas
    ```php
        SHOW TABLES;
    ```

# Rodar a Apliação

Após realizar todos estes passos a sua aplicação deverá estar apta para funcionar localmente. Para rodar a aplicação realize os ultimos passos.

1. **Instalar as Dependências**
    ```php
        composer install
    ```

1. **Habilitar a porta no ufw da VM**
    ```php
        ufw allow 8000
    ```

2. **Rodar a aplicação**
    <br> Basta entrar na pasta do projeto do RPE
    ```php
        php -S 0.0.0.0:8000 -t public 
    ```
    Assim a aplicação estará rodando com a porta 8000 aberta para conexao, basta se conectar com o notebook utilizando o endereço ip da VM com sua porta como url<br>

    http://192.168.137.25:8000
    


   # Burlar o Keycloak

    Após a sua aplicação estiver no ar , quando tentar acessar ela , ira redirecionar para o keycloak , porem voce nao estará na rede corporativa, então será necessário "burlar" o Keycloak.<br>
    Vamos criar um Middleware para interceptar todas as requisições de rotas no sistema, para forçar uma autenticação.


1. **Criação do MiddleWare**
    ```php
       php artisan make:Middleware FakeKeycloakLogin
    ```

2. **Dentro do Middleware (FakeKeycloakLogin)**
    <br> 
    Dentro da função handle 
    ```php
    //importe as bibliotecas abaixo:
    // use Closure;
    // use App\User
    // use illuminate\Support\Facades\Auth

         public function handle($request, Closure $next){
            if(!Auth::check() && env('APP_FAKE_LOGIN',false)){
                $user = User::find(480); 
                // 480 = id de um usuario no banco de dados , utilize sempre o seu user , verifique no banco qual é o  seu id e troque para o seu.
                if($user){
                    Auth::setUser($user);
                } 
            } 
            return $next($request)
         }
    
    ```

3. **Dentro do arquivo .env**
<br>Adicione a variavel em qualquer lugar do arquivo
    ```php
        APP_FAKE_LOGIN = true
        // true ele burla , false ele usa o keycloak
    ```

3. **Dentro do arquivo app/Http/Kernel.php**
   ```php
         protected $middleware = [
             \App\Http\Middleware\FakeLoginKey::class,
             ...
             //restante das classes
         ]
         protected $middlewareGroups = [
             'web' => [
             \App\Http\Middleware\FakeLoginKey::class,
             ...
             //restante das classes
             ],
             ...
             //restante das classes
         ]    
   ```

Assim, voce tera acesso ao RPE sem precisar estar na rede coporativa, podendo utilizar todos os serviços disponíveis.



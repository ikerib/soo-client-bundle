# Integración de `pasaia/sso-client-bundle`

Este bundle permite que cualquier aplicación Symfony del Ayuntamiento de Pasaia
delegue su autenticación en el SSO Municipal.  
El flujo es **Authorization Code + PKCE**, implementado por
[`drenso/symfony-oidc-bundle`](https://github.com/Drenso/symfony-oidc) (librería
de terceros mantenida activamente); nuestro bundle es una capa de configuración
y convención por encima.

---

## Requisitos previos

| Requisito | Versión mínima |
|-----------|---------------|
| PHP | 8.2 |
| Symfony | 6.4 o 7.x |
| Extensiones PHP | `curl`, `json`, `mbstring` |

---

## 1. Registrar la aplicación en el SSO

Antes de instalar el bundle, la aplicación debe estar registrada como cliente OIDC
en el SSO. Ejecuta este comando **en el servidor del SSO**:

```bash
docker compose exec app php bin/console app:oidc:client:create \
    --name="nombre-de-la-app" \
    --redirect-uri="https://miapp.pasaia.eus/sso/callback" \
    --scopes="openid profile email roles"
```

Guarda el `client_id` y el `client_secret` que devuelve el comando.

Para asignar grupos LDAP a roles de la app:

```bash
docker compose exec app php bin/console app:client:configure-access \
    --client="nombre-de-la-app" \
    --group="CN=Teknikariak,OU=Taldeak,DC=pasaia,DC=eus" \
    --role="ROLE_ADMIN"
```

---

## 2. Instalar el bundle

```bash
composer require pasaia/sso-client-bundle
```

Esto instala también `drenso/symfony-oidc-bundle` como dependencia transitiva.
Las actualizaciones de seguridad de la capa OIDC (verificación de firma JWT, JWKS,
PKCE) llegan automáticamente vía `composer update drenso/symfony-oidc-bundle`.

Registra los bundles en `config/bundles.php`:

```php
return [
    // ...
    Drenso\OidcBundle\DrensoOidcBundle::class => ['all' => true],
    Pasaia\SsoClientBundle\PasaiaSsoClientBundle::class => ['all' => true],
];
```

---

## 3. Variables de entorno (las 4 requeridas)

Añade al `.env` de la aplicación:

```dotenv
# URL base del SSO (sin barra final).
SSO_ISSUER_URL=https://sso.pasaia.eus

# Credenciales obtenidas al registrar la app en el SSO (paso 1).
SSO_CLIENT_ID=nombre-de-la-app
SSO_CLIENT_SECRET=el-secreto-generado

# URL completa a la que el SSO redirige tras autenticar.
# El path /sso/callback es el valor por defecto del check_path.
# Debe coincidir EXACTAMENTE con el redirect_uri registrado en el SSO.
SSO_REDIRECT_URI=https://miapp.pasaia.eus/sso/callback
```

Variables opcionales:

```dotenv
# Scopes adicionales (por defecto: openid profile email roles).
# Solo añade si necesitas más claims; "roles" es necesario para los permisos.
# SSO_SCOPES=openid profile email roles

# URL a la que redirige el SSO tras cerrar sesión globalmente.
# Si no se define, el SSO redirige a su propia página de login.
# SSO_POST_LOGOUT_REDIRECT_URI=https://miapp.pasaia.eus/
```

El bundle traduce automáticamente estas variables a la configuración interna de
`drenso/symfony-oidc-bundle`. No necesitas crear ningún `drenso_oidc.yaml`.

---

## 4. Configurar el firewall (`config/packages/security.yaml`)

```yaml
security:
    providers:
        pasaia_oidc:
            id: Pasaia\SsoClientBundle\Security\OidcUserProvider

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        main:
            lazy: true
            provider: pasaia_oidc

            oidc:
                # Ruta donde el SSO redirige tras autenticar.
                # Debe coincidir con el path de SSO_REDIRECT_URI.
                check_path: /sso/callback

                # Ruta a la que se envía al usuario no autenticado.
                # El bundle registra esta ruta automáticamente.
                login_path: /sso/login

                # Claim usado como identificador único del usuario.
                user_identifier_property: sub

                # Obtiene todos los claims (name, email, roles, auth_method, dni)
                # del endpoint /oidc/userinfo del SSO.
                enable_retrieve_user_info: true

                # Al hacer logout, redirige al end_session_endpoint del SSO
                # para cerrar también la sesión global.
                enable_end_session_listener: true

            logout:
                path: /sso/logout

    access_control:
        - { path: ^/sso,    roles: PUBLIC_ACCESS }
        - { path: ^/public, roles: PUBLIC_ACCESS }
        - { path: ^/,       roles: ROLE_USER }
```

---

## 5. Registrar las rutas del bundle (`config/routes.yaml`)

```yaml
pasaia_sso_client:
    resource: '@PasaiaSsoClientBundle/config/routes.yaml'

# Tus rutas de la aplicación
controllers:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
    type: attribute
```

Esto registra la ruta `GET /sso/login` que inicia el flujo OIDC.

---

## 6. Usar el usuario autenticado

El bundle proporciona `OidcUser` como principal de Symfony Security.
Los roles vienen del claim `roles` del ID token — calculados por el SSO a partir
de los grupos LDAP del usuario para esta aplicación concreta. **La app no consulta
LDAP en ningún momento.**

```php
use Pasaia\SsoClientBundle\Security\OidcUser;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
public function adminPage(#[CurrentUser] OidcUser $user): Response
{
    $username    = $user->getUserIdentifier(); // claim "sub"
    $displayName = $user->getName();           // claim "name"
    $email       = $user->getEmail();          // claim "email"
    $dni         = $user->getDni();            // claim "dni" (solo si scope incluye "dni")
    $authMethod  = $user->getAuthMethod();     // "password" | "certificate"
    $roles       = $user->getRoles();          // array de roles Symfony

    // ...
}
```

En Twig:

```twig
{% if is_granted('ROLE_ADMIN') %}
    <p>Hola, {{ app.user.name }}!</p>
{% endif %}
```

---

## 7. Resumen del flujo de extremo a extremo

```
Usuario  →  App (/ruta-protegida)  →  Firewall  →  /sso/login
  ↓                                                      ↓
  ←←←←←←←  SSO (login)  ←←←←←←←←←←←←←←←←←←←←←←←←←←←←←
                 ↓
         SSO emite ID token con:
           sub, name, email, roles (para esta app), auth_method
                 ↓
  App (/sso/callback)  →  drenso verifica firma JWT + PKCE
                 ↓
         OidcUserProvider::ensureUserExists()
         OidcUserProvider::loadOidcUser()  →  OidcUser en sesión
                 ↓
         Usuario autenticado  →  /ruta-protegida
```

---

## 8. Nota sobre la seguridad criptográfica

La verificación del ID token (firma RS256 contra el JWKS del SSO, validación de
`iss`, `aud`, `exp`, `iat`, PKCE, `state`, `nonce`) la realiza íntegramente
`drenso/symfony-oidc-bundle`. Nuestro bundle **no reimplementa** ninguna de esas
operaciones. Esto es deliberado: el código de seguridad de un cliente OIDC es
donde un error sutil puede convertirse en un agujero de autenticación; usar una
librería probada por miles de proyectos y que recibe correcciones vía
`composer update` es más seguro que una implementación propia.

Para actualizar la capa OIDC:

```bash
composer update drenso/symfony-oidc-bundle
```

---

## 9. Mapa de claims del ID token → OidcUser

| Claim OIDC | Método en OidcUser | Notas |
|---|---|---|
| `sub` | `getUserIdentifier()` | Nombre de usuario LDAP |
| `name` | `getName()` | Nombre completo |
| `email` | `getEmail()` | Correo del directorio |
| `roles` | `getRoles()` | Roles Symfony calculados por el SSO |
| `auth_method` | `getAuthMethod()` | `password` o `certificate` |
| `dni` | `getDni()` | Solo con scope `dni`; solo vía Giltza |

---

## 10. Preguntas frecuentes

**¿Cómo sé qué roles tiene un usuario para mi app?**  
El SSO calcula los roles en el momento del login en función de los grupos LDAP del
usuario y el mapeo configurado para tu aplicación (con `app:client:configure-access`).
El ID token ya lleva los roles calculados; la app los recibe directamente.

**¿El usuario tiene que volver a hacer login si sus grupos LDAP cambian?**  
Sí: los roles se recalculan en cada login, no en cada request. Si necesitas que los
cambios se propaguen sin nuevo login, sobrescribe `OidcUserProvider::refreshUser()`
en tu app para llamar al endpoint `/oidc/userinfo` del SSO.

**¿Puedo usar `remember_me`?**  
Sí. En el firewall, añade `enable_remember_me: true` bajo `oidc:` y configura el
bloque `remember_me:` de Symfony normalmente.

**¿Y si el SSO no está disponible?**  
drenso lanzará una excepción que Symfony Security convierte en HTTP 500. Puedes
capturarla configurando un `failure_path` en el firewall:
```yaml
oidc:
    failure_path: /error/sso
```

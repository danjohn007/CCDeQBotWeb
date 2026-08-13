/**
 * Script de verificación estática del CRM Alunay
 * Valida: rutas API, consultas SQL vs esquema BD, y referencias frontend
 */
const fs = require('fs');
const path = require('path');

let errores = [];
let ok = 0;

function check(condicion, mensaje) {
  if (condicion) {
    ok++;
  } else {
    errores.push(mensaje);
  }
}

function checkFile(filePath) {
  try {
    return fs.readFileSync(filePath, 'utf8');
  } catch {
    return null;
  }
}

console.log('=== VERIFICACIÓN ESTÁTICA DEL CRM ===\n');

// 1. Archivos esenciales existen
console.log('[1] Archivos esenciales');
const archivosNecesarios = [
  'ccdeqbot/index.html',
  'ccdeqbot/.htaccess',
  'ccdeqbot/front/index.html',
  'ccdeqbot/front/.htaccess',
  'ccdeqbot/front/assets/index-B_2G58HW.js',
  'ccdeqbot/front/assets/index-olXNBsYt.css',
  'ccdeqbot/back/api/index.php',
  'ccdeqbot/back/.htaccess',
  'ccdeqbot/back/crear-admin.php',
  'ccdeqbot/back/app/bootstrap.php',
  'ccdeqbot/back/app/config/config.php',
  'ccdeqbot/back/app/Core/Auth.php',
  'ccdeqbot/back/app/Core/Csrf.php',
  'ccdeqbot/back/app/Core/Database.php',
  'ccdeqbot/back/app/Core/Request.php',
  'ccdeqbot/back/app/Core/Response.php',
  'ccdeqbot/back/app/Core/Audit.php',
  'ccdeqbot/back/app/Controllers/AuthController.php',
  'ccdeqbot/back/app/Controllers/ContactController.php',
  'ccdeqbot/back/app/Controllers/DashboardController.php',
  'ccdeqbot/back/app/Controllers/QuoteController.php',
  'ccdeqbot/back/app/Controllers/UserController.php',
  'ccdeqbot/back/app/Controllers/VideoController.php',
  'database/crmcamar_allunay.sql',
];
archivosNecesarios.forEach(f => {
  check(fs.existsSync(f), `FALTA: ${f}`);
});
console.log(`  - ${ok} archivos esenciales verificados`);
const okArchivos = ok;
ok = 0;

// 2. Verificar palabras correctas (corrección del commit "corregir palabra bot")
console.log('\n[2] Verificación de corrección de palabra "bot"');
const js = checkFile('ccdeqbot/front/assets/index-B_2G58HW.js') || '';
const backendIndex = checkFile('ccdeqbot/back/api/index.php') || '';
const crearAdmin = checkFile('ccdeqbot/back/crear-admin.php') || '';
const frontIndex = checkFile('ccdeqbot/front/index.html') || '';
const ccdeqIndex = checkFile('ccdeqbot/index.html') || '';

// Referencias correctas - "AllunayBOT" en JS compilado
check(js.includes('AllunayBOT'), 'JS debe contener "AllunayBOT" (sidebar/login/dashboard)');
// Verificar que no queden variantes incorrectas
const incorrectas = ['Allunay Bot', 'Allunaybot', 'Allunay bot', 'Allunay-Bot', 'Allunay_Bot'];
incorrectas.forEach(v => {
  check(!js.includes(v), `JS NO debe contener "${v}"`);
});
// API debe decir "Alunay CRM API"
check(backendIndex.includes("'Alunay CRM API'"), 'API health debe decir "Alunay CRM API"');
// crear-admin debe decir "Alunay CRM"
check(crearAdmin.includes('<h1>Alunay CRM</h1>'), 'crear-admin.php debe tener "Alunay CRM"');
check(crearAdmin.includes('Alunay CRM') && !crearAdmin.includes('CCdeQbot'), 'crear-admin.php no debe tener CCdeQbot');
// Front index debe decir "Alunay CRM"
check(frontIndex.includes('<title>Alunay CRM</title>'), 'front/index.html título debe ser "Alunay CRM"');
// Index raíz debe redirigir a ./front/
check(ccdeqIndex.includes("window.location.replace('./front/')"), 'index.html debe redirigir a ./front/');
// No debe quedar CCdeQbot en el frontend
const jsLowe = js.toLowerCase();
check(!jsLowe.includes('ccdeqbot'), 'JS compilado no debe contener "CCdeQbot"');
console.log(`  - ${ok} verificaciones de texto correctas`);
const okTexto = ok;
ok = 0;

// 3. Validar rutas API vs métodos de controladores
console.log('\n[3] Rutas API vs Controladores');

// Rutas definidas en index.php
const apiContent = backendIndex;
const rutasEsperadas = [
  'GET /health',
  'POST /auth/login',
  'GET /auth/me',
  'POST /auth/logout',
  'GET /dashboard/resumen',
  'GET /contactos',
  'GET /contactos/{id}',
  'PUT /contactos/{id}',
  'PATCH /contactos/{id}/clasificacion',
  'POST /contactos/{id}/notas',
  'POST /contactos/{id}/etiquetas',
  'DELETE /contactos/{id}/etiquetas/{idEtiqueta}',
  'GET /cotizaciones',
  'PATCH /cotizaciones/{id}/estado',
  'GET /usuarios',
  'POST /usuarios',
  'PATCH /usuarios/{id}/estatus',
  'GET /catalogos/asesores',
  'GET /catalogos/etiquetas',
  'GET /videos',
  'POST /videos',
  'PUT /videos/{id}',
  'PATCH /videos/{id}/estatus',
  'DELETE /videos/{id}',
];

const nombresRuta = [
  "'/health'",
  "'/auth/login'",
  "'/auth/me'", "'/auth/logout'",
  "'/dashboard/resumen'",
  "'/contactos'",
  "'#^/contactos/(\\d+)$#'", "'#^/contactos/(\\d+)$#'",
  "'#^/contactos/(\\d+)/clasificacion$#'",
  "'#^/contactos/(\\d+)/notas$#'",
  "'#^/contactos/(\\d+)/etiquetas$#'",
  "'#^/contactos/(\\d+)/etiquetas/(\\d+)$#'",
  "'/cotizaciones'",
  "'#^/cotizaciones/(\\d+)/estado$#'",
  "'/usuarios'", "'/usuarios'",
  "'#^/usuarios/(\\d+)/estatus$#'",
  "'/catalogos/asesores'", "'/catalogos/etiquetas'",
  "'/videos'", "'/videos'",
  "'#^/videos/(\\d+)$#'",
  "'#^/videos/(\\d+)/estatus$#'",
  "'#^/videos/(\\d+)$#'",
];

nombresRuta.forEach(r => {
  check(apiContent.includes(r), `API debe tener ruta ${r}`);
});

// Controladores deben tener métodos
const controllers = {
  'AuthController.php': ['login', 'me', 'logout'],
  'ContactController.php': ['index', 'show', 'update', 'classify', 'addNote', 'addTag', 'removeTag'],
  'DashboardController.php': ['summary'],
  'QuoteController.php': ['index', 'updateState'],
  'UserController.php': ['index', 'create', 'toggleStatus', 'advisors', 'tags'],
  'VideoController.php': ['index', 'create', 'update', 'toggleStatus', 'delete'],
};

for (const [archivo, metodos] of Object.entries(controllers)) {
  const contenido = checkFile(`ccdeqbot/back/app/Controllers/${archivo}`) || '';
  metodos.forEach(m => {
    check(contenido.includes(`public static function ${m}`), `Controller ${archivo} debe tener método ${m}`);
  });
}
console.log(`  - ${ok} verificaciones de rutas/controladores correctas`);
const okRutas = ok;
ok = 0;

// 4. Verificar integración BD (columnas usadas en consultas existen en esquema)
console.log('\n[4] Integración Base de Datos');
const sql = checkFile('database/crmcamar_allunay.sql') || '';

// Tablas referenciadas por los controladores
const tablasRequeridas = [
  'auditoria', 'contactos', 'contacto_etiquetas', 'conversaciones',
  'cotizaciones', 'cotizacion_archivos', 'cotizacion_detalles', 'etiquetas',
  'historial_estados', 'interacciones', 'notas_contacto', 'productos_servicios',
  'sesiones_bot', 'solicitudes', 'usuarios_sistema', 'videos', 'ventas'
];
tablasRequeridas.forEach(t => {
  check(sql.includes(`CREATE TABLE \`${t}\``), `BD debe tener tabla ${t}`);
});

// Columnas críticas que usan los controladores
const columnasVerificadas = {
  'usuarios_sistema': ['id_usuario', 'nombre', 'apellidos', 'correo', 'password_hash', 'rol', 'activo', 'intentos_fallidos', 'bloqueado_hasta', 'ultimo_acceso'],
  'videos': ['id_video', 'titulo', 'url', 'activo', 'creado_en'],
  'contactos': ['id_contacto', 'whatsapp', 'nombre_completo', 'correo', 'empresa', 'tipo_contacto', 'etapa_comercial', 'id_asesor', 'proximo_seguimiento', 'notas_generales', 'activo', 'creado_en', 'convertido_cliente_en', 'motivo_clasificacion'],
  'auditoria': ['id_usuario', 'accion', 'entidad', 'id_entidad', 'descripcion', 'ip', 'user_agent', 'datos_anteriores', 'datos_nuevos'],
  'cotizaciones': ['id_cotizacion', 'folio', 'estado', 'enviada_en', 'aceptada_en', 'solicitada_en'],
  'historial_estados': ['id_contacto', 'id_usuario', 'tipo_anterior', 'tipo_nuevo', 'etapa_anterior', 'etapa_nueva', 'motivo', 'origen_cambio'],
  'notas_contacto': ['id_nota', 'id_contacto', 'id_usuario', 'nota', 'es_privada', 'creada_en'],
  'contacto_etiquetas': ['id_contacto', 'id_etiqueta', 'id_asignado_por'],
  'interacciones': ['id_interaccion', 'direccion', 'tipo_mensaje', 'paso_flujo', 'contenido', 'estado_envio', 'registrado_en'],
  'etiquetas': ['id_etiqueta', 'nombre', 'slug', 'color_fondo', 'color_texto', 'activo'],
};

for (const [tabla, columnas] of Object.entries(columnasVerificadas)) {
  // Extraer el bloque CREATE TABLE de la tabla
  const match = sql.match(new RegExp(`CREATE TABLE \`${tabla}\` \\(([\\s\\S]*?)\\) ENGINE`));
  if (match) {
    const bloque = match[1];
    columnas.forEach(col => {
      check(bloque.includes(`\`${col}\``), `BD tabla ${tabla} debe tener columna ${col}`);
    });
  } else {
    check(false, `No se pudo extraer CREATE TABLE de ${tabla}`);
  }
}
console.log(`  - ${ok} verificaciones de BD correctas`);
const okBD = ok;
ok = 0;

// 5. Verificar config.php
console.log('\n[5] Configuración');
const config = checkFile('ccdeqbot/back/app/config/config.php') || '';
check(config.includes("'app_env' => 'production'"), 'app_env debe ser production');
check(config.includes("'db'"), 'Config debe tener sección db');
check(config.includes("'name' => 'crmcamar_allunay'"), 'BD name debe ser crmcamar_allunay');
check(config.includes("'setup_key'"), 'Config debe tener setup_key');
check(config.includes("'allowed_origins'"), 'Config debe tener allowed_origins');
check(config.includes("'session'"), 'Config debe tener sección session');
check(config.includes("'base_path'"), 'Config debe tener base_path');
console.log(`  - ${ok} verificaciones de configuración correctas`);
const okConfig = ok;
ok = 0;

// 6. .htaccess
console.log('\n[6] .htaccess');
const htroot = checkFile('ccdeqbot/.htaccess') || '';
check(htroot.includes('RewriteRule ^back/app(?:/|$) - [F,L,NC]'), 'Raíz .htaccess bloquea back/app');
check(htroot.includes('DirectoryIndex index.html'), 'Raíz .htaccess tiene DirectoryIndex');

const htback = checkFile('ccdeqbot/back/.htaccess') || '';
check(htback.includes('RewriteRule ^app(?:/|$) - [F,L,NC]'), 'back .htaccess bloquea app');

const htfront = checkFile('ccdeqbot/front/.htaccess') || '';
check(htfront.includes('RewriteRule ^ index.html [L]'), 'front .htaccess redirige a index.html');
check(htfront.includes('RewriteCond %{REQUEST_FILENAME} !-f'), 'front .htaccess condición para archivos');
check(htfront.includes('RewriteCond %{REQUEST_FILENAME} !-d'), 'front .htaccess condición para directorios');
console.log(`  - ${ok} verificaciones .htaccess correctas`);
const okHt = ok;
ok = 0;

// 7. Seguridad CSRF
console.log('\n[7] Seguridad CSRF');
const csrf = checkFile('ccdeqbot/back/app/Core/Csrf.php') || '';
check(csrf.includes('hash_equals'), 'CSRF usa hash_equals para comparación segura');
check(csrf.includes('X-CSRF-Token'), 'CSRF verifica header X-CSRF-Token');
check(csrf.includes(', 419)'), 'CSRF responde con código 419');
console.log(`  - ${ok} verificaciones CSRF correctas`);
const okCsrf = ok;
ok = 0;

// 8. Frontend carga los recursos correctamente
console.log('\n[8] Frontend referencias');
check(frontIndex.includes('index-B_2G58HW.js'), 'front/index.html referencia JS correcto');
check(frontIndex.includes('index-olXNBsYt.css'), 'front/index.html referencia CSS correcto');
check(frontIndex.includes('<div id="root"></div>'), 'front/index.html tiene div#root');
check(fs.existsSync('ccdeqbot/front/assets/index-B_2G58HW.js'), 'JS compilado existe');
check(fs.existsSync('ccdeqbot/front/assets/index-olXNBsYt.css'), 'CSS compilado existe');

// Verificar que el JS referencia correctamente paths relativos de la API
check(js.includes('../../back/api'), 'JS apunta a ../../back/api');
console.log(`  - ${ok} verificaciones frontend correctas`);
const okFront = ok;

// Resumen
const total = okArchivos + okTexto + okRutas + okBD + okConfig + okHt + okCsrf + okFront;
console.log('\n=== RESUMEN ===');
console.log(`Archivos esenciales: ${okArchivos} ✓`);
console.log(`Corrección palabra bot: ${okTexto} ✓`);
console.log(`Rutas API/Controladores: ${okRutas} ✓`);
console.log(`Integración BD: ${okBD} ✓`);
console.log(`Configuración: ${okConfig} ✓`);
console.log(`.htaccess: ${okHt} ✓`);
console.log(`Seguridad CSRF: ${okCsrf} ✓`);
console.log(`Frontend: ${okFront} ✓`);
console.log(`TOTAL: ${total} verificaciones`);
if (errores.length > 0) {
  console.log(`\n⚠️  ERRORES ENCONTRADOS (${errores.length}):`);
  errores.forEach(e => console.log(`  ❌ ${e}`));
  process.exit(1);
} else {
  console.log('\n✅ TODAS LAS VERIFICACIONES PASARON CORRECTAMENTE');
}
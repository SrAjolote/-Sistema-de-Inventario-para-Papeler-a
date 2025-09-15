# 📋 Sistema de Inventario para Papelería

Sistema completo de gestión de inventario desarrollado específicamente para papelerías, con interfaz moderna y todas las funcionalidades necesarias para administrar productos, categorías, proveedores y reportes.

## ✨ Características

- **Gestión completa de productos** con control de stock, precios y categorías
- **Sistema de categorías** organizado para productos de papelería
- **Control de inventario** con registro de entradas y salidas
- **Gestión de proveedores** con información de contacto completa
- **Reportes y estadísticas** en tiempo real
- **Interfaz moderna** con animaciones y diseño responsivo
- **Sistema de usuarios** con diferentes niveles de acceso

## 🛠️ Tecnologías Utilizadas

- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS:** Bootstrap 5.3.0
- **Iconos:** Font Awesome 6.4.0
- **Backend:** PHP 7.4+
- **Base de datos:** MySQL 5.7+
- **Otros:** Chart.js para gráficos, DataTables para tablas

## 📦 Módulos Principales

1. **Dashboard** - Vista general con estadísticas y alertas
2. **Productos** - CRUD completo de productos con imágenes
3. **Categorías** - Gestión de categorías y subcategorías
4. **Inventario** - Control de stock y movimientos
5. **Proveedores** - Administración de proveedores
6. **Reportes** - Generación de reportes en PDF/Excel
7. **Usuarios** - Sistema de autenticación y permisos

## 🚀 Instalación

1. Clonar el repositorio:
```bash
git clone https://github.com/tuusuario/inventario-papeleria.git
```

2. Importar la base de datos:
```bash
mysql -u usuario -p inventario_papeleria < database/inventario_papeleria.sql
```

3. Configurar las variables de entorno en `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventario_papeleria');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');
```

4. Acceder al sistema desde el navegador:
```
http://localhost/inventario-papeleria
```

## 🔐 Credenciales por Defecto

- **Administrador:** admin@papeleria.com / admin123
- **Usuario:** usuario@papeleria.com / usuario123

## 📊 Estructura de la Base de Datos

El sistema incluye las siguientes tablas principales:
- `usuarios` - Información de usuarios del sistema
- `categorias` - Categorías de productos
- `productos` - Inventario de productos
- `proveedores` - Datos de proveedores
- `movimientos` - Registro de entradas/salidas
- `configuraciones` - Configuración del sistema

## 🎨 Personalización

Puede personalizar los colores del sistema modificando las variables CSS en `assets/css/styles.css`:

```css
:root {
    --primary: #4e73df;
    --secondary: #6f42c1;
    --success: #1cc88a;
    /* ... más variables */
}
```

## 📱 Diseño Responsivo

El sistema está completamente optimizado para:
- 📱 Dispositivos móviles
- 💻 Tabletas
- 🖥️ Desktop

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor:

1. Hacer un Fork del proyecto
2. Crear una rama para su feature (`git checkout -b feature/AmazingFeature`)
3. Commit de los cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## 📞 Soporte

Si tienes preguntas o necesitas ayuda, puedes:
- Abrir un issue en GitHub
- Contactar al equipo de desarrollo: gsdavid151006@gmail.com

## 🔄 Historial de Versiones

- **v1.0.0** - Lanzamiento inicial (01 Nov 2023)
- **v1.1.0** - Agregado módulo de reportes (15 Nov 2023)
- **v1.2.0** - Mejoras en responsive design (30 Nov 2023)

---

**Nota:** Este sistema requiere PHP 7.4 o superior y MySQL 5.7 o superior para funcionar correctamente.

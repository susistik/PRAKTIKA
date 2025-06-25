document.addEventListener('DOMContentLoaded', function() {
    // Обработка выбора роли
    document.querySelectorAll('.role-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('selected-role').value = this.dataset.role;

            if (this.dataset.role === 'admin') {
                document.getElementById('admin-password-field').style.display = 'block';
            } else {
                document.getElementById('admin-password-field').style.display = 'none';
            }

            document.getElementById('role-form').style.display = 'block';
        });
    });

    // Фильтрация меню
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const category = this.dataset.category;
            document.querySelectorAll('.menu-item').forEach(item => {
                if (category === 'all' || item.dataset.category === category) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    // Поиск по меню
    document.getElementById('menu-search').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('.menu-item').forEach(item => {
            const dishName = item.querySelector('.menu-title').textContent.toLowerCase();
            if (dishName.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Плавная прокрутка
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                window.scrollTo({
                    top: target.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });
});
document.addEventListener('DOMContentLoaded', function() {
    // Текущий пользователь (в реальном приложении данные получаются с сервера)
    const currentUser = {
        id: 1,
        name: "Иван Иванов",
        role: "moderator" // admin, moderator, user
    };

    // Применяем настройки доступа для роли
    function applyRoleSettings() {
        // Скрываем админ-панель для не-админов
        if (currentUser.role !== 'admin') {
            document.querySelectorAll('.admin-only').forEach(el => {
                el.style.display = 'none';
            });
        }

        // Скрываем модер-панель для не-модеров
        if (currentUser.role !== 'admin' && currentUser.role !== 'moderator') {
            document.querySelectorAll('.moderator-only').forEach(el => {
                el.style.display = 'none';
            });
        }

        // Обновляем интерфейс выбора роли
        document.querySelectorAll('.role-btn').forEach(btn => {
            if (btn.dataset.role === 'admin' && currentUser.role !== 'admin') {
                btn.disabled = true;
                btn.title = "Требуются права администратора";
            }
        });
    }

    // Проверка прав доступа
    function checkPermission(requiredRole) {
        const roles = {
            'admin': 3,
            'moderator': 2,
            'user': 1
        };
        return roles[currentUser.role] >= roles[requiredRole];
    }

    // Защищенная функция (только для админов)
    function adminAction() {
        if (!checkPermission('admin')) {
            alert("Доступ запрещен! Требуются права администратора.");
            return false;
        }
        console.log("Выполнено админ-действие");
        return true;
    }

    // Инициализация ролей
    applyRoleSettings();

    // Обработка выбора роли (с проверкой прав)
    document.querySelectorAll('.role-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Проверка прав для роли admin
            if (this.dataset.role === 'admin' && !checkPermission('admin')) {
                alert("Недостаточно прав для выбора роли администратора!");
                return;
            }

            document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('selected-role').value = this.dataset.role;

            document.getElementById('admin-password-field').style.display = 
                this.dataset.role === 'admin' ? 'block' : 'none';

            document.getElementById('role-form').style.display = 'block';
        });
    });

    // Фильтрация меню (разные права для разных ролей)
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const category = this.dataset.category;

            // Проверка доступа к premium-категории
            if (category === 'premium' && !checkPermission('moderator')) {
                alert("Premium категория доступна только модераторам и администраторам!");
                return;
            }

            document.querySelectorAll('.menu-item').forEach(item => {
                if (category === 'all' || item.dataset.category === category) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    // Поиск по меню (с ограничением для пользователей)
    document.getElementById('menu-search').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();

        document.querySelectorAll('.menu-item').forEach(item => {
            const dishName = item.querySelector('.menu-title').textContent.toLowerCase();

            // Скрываем premium-блюда для обычных пользователей
            const isPremium = item.dataset.category === 'premium';
            if (isPremium && !checkPermission('moderator')) {
                item.style.display = 'none';
                return;
            }

            if (dishName.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Админ-действие (пример вызова защищенной функции)
    document.getElementById('admin-action-btn')?.addEventListener('click', function(e) {
        e.preventDefault();
        adminAction();
    });

    // Плавная прокрутка (доступна всем)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                window.scrollTo({
                    top: target.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Инициализация интерфейса в зависимости от роли
    console.log(`Текущий пользователь: ${currentUser.name}, роль: ${currentUser.role}`);
});

<?php
namespace Views;

class ProfileTemplate extends BaseTemplate
{
    public static function getTemplate($userOrders = []): string
    {
        $template = parent::getTemplate();
        $title = 'Личный кабинет';
        if (session_status() === PHP_SESSION_NONE) session_start();

        $success = $_SESSION['profile_success'] ?? '';
        unset($_SESSION['profile_success']);
        $alertHtml = $success ? '<div class="alert alert-success">' . $success . '</div>' : '';

        // Генерация HTML таблицы заказов
        $ordersHtml = '';
        if (empty($userOrders)) {
            $ordersHtml = '<tr><td colspan="5" class="text-center py-3 text-muted">История заказов пуста</td></tr>';
        } else {
            $statusMap = [
                'new' => ['label' => 'Новый', 'class' => 'warning'],
                'processing' => ['label' => 'Обработка', 'class' => 'info'],
                'paid' => ['label' => 'Оплачен', 'class' => 'primary'],
                'shipped' => ['label' => 'Отправлен', 'class' => 'secondary'],
                'completed' => ['label' => 'Выполнен', 'class' => 'success'],
                'cancelled' => ['label' => 'Отменён', 'class' => 'danger']
            ];
            
            foreach ($userOrders as $order) {
                $status = $order['status'];
                $statusInfo = $statusMap[$status] ?? ['label' => $status, 'class' => 'secondary'];
                
                $ordersHtml .= '<tr>
                    <td>' . htmlspecialchars($order['order_number']) . '</td>
                    <td>' . date('d.m.Y H:i', strtotime($order['created_at'])) . '</td>
                    <td>' . (int)$order['items_count'] . ' шт.</td>
                    <td><strong>' . number_format($order['total_price'], 0, '.', ' ') . ' ₽</strong></td>
                    <td><span class="badge bg-' . $statusInfo['class'] . '">' . $statusInfo['label'] . '</span></td>
                </tr>';
            }
        }

        $content = '
        <style>
            .profile-card { background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); margin-bottom: 2rem; }
            .btn-update { background: linear-gradient(135deg, #ff6b35, #f7931e); border: none; padding: 0.8rem; color: white; font-weight: 700; border-radius: 50px; }
            .orders-table th { background: #f8f9fa; }
        </style>
        <section class="container py-4">
            <h2 class="mb-4"> ' . htmlspecialchars($_SESSION['user_name']) . '</h2>
            ' . $alertHtml . '
            
            <div class="profile-card">
                <h4 class="mb-3"> История заказов</h4>
                <div class="table-responsive">
                    <table class="table orders-table align-middle">
                        <thead>
                            <tr>
                                <th>№ Заказа</th>
                                <th>Дата</th>
                                <th>Товаров</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>' . $ordersHtml . '</tbody>
                    </table>
                </div>
            </div>

            <div class="profile-card" style="max-width: 500px;">
                <h4 class="mb-3">⚙️ Настройки</h4>
                <form method="POST" action="/profile/update">
                    <div class="mb-3"><label class="form-label">Имя</label><input type="text" name="name" class="form-control" value="' . htmlspecialchars($_SESSION['user_name']) . '" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" value="' . htmlspecialchars($_SESSION['user_email']) . '" disabled></div>
                    <button type="submit" class="btn-update">💾 Сохранить</button>
                </form>
            </div>

            <form method="POST" action="/logout">
                <button type="submit" class="btn btn-outline-danger w-100 rounded-pill">🚪 Выйти</button>
            </form>
        </section>';
        
        return str_replace(['{{TITLE}}', '{{CONTENT}}'], [$title, $content], $template);
    }
}
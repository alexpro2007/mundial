<?php
// footer.php - Footer global, Disclaimer legal de afiliados y Banner de Cookies
?>

<!-- Disclaimer de Afiliados y Juego Responsable -->
<div style="background-color: rgba(15, 23, 42, 0.9); border-top: 1px solid var(--border-glass); padding: 20px; font-size: 0.75rem; color: #94a3b8; text-align: justify; margin-top: 60px;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <h4 style="color: #cbd5e1; margin-bottom: 10px; font-size: 0.85rem;">Descargo de Responsabilidad Comercial y Juego Responsable</h4>
        <p style="margin-bottom: 10px;">Este sitio web contiene enlaces de afiliados de operadores de juego online. Si haces clic en estos enlaces y te registras en la plataforma, podríamos recibir una comisión económica sin coste adicional para ti.</p>
        <p><strong>Atención:</strong> La información y los enlaces de apuestas mostrados están dirigidos estrictamente a personas <strong>mayores de 18 años</strong>. El juego online puede generar adicción. Por favor, juega de forma responsable y solo si puedes permitírtelo. Para más información, visita <a href="https://www.jugarbien.es" target="_blank" rel="noopener nofollow" style="color: var(--accent-blue); text-decoration: none;">JugarBien.es</a>.</p>
        
        <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center; justify-content: center;">
            <span style="display:inline-block; border: 1px solid #cbd5e1; border-radius: 50%; width: 30px; height: 30px; line-height: 28px; text-align: center; font-weight: bold; color: #cbd5e1; font-size: 0.7rem;">+18</span>
            <a href="https://www.jugarbien.es" target="_blank" rel="noopener nofollow" style="color: #cbd5e1; font-weight: bold; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 0.75rem;">JugarBien</a>
        </div>
    </div>
</div>

<!-- Footer Standard -->
<footer style="text-align:center; padding:30px 20px; border-top:1px solid var(--border-glass); color:#64748b; font-size:0.85rem; background: var(--bg-card);">
    <p>&copy; <?php echo date('Y'); ?> 5 Ligas Europa. <?php echo htmlspecialchars(__('footer_rights') ?? 'Todos los derechos reservados.'); ?></p>
    <p style="margin-top:10px; font-size:0.75rem;"><?php echo __('footer_note_index') ?? ''; ?></p>
</footer>

<!-- Cookie Banner -->
<div id="cookie-banner" style="display: none; position: fixed; bottom: 0; left: 0; right: 0; background-color: #0f172a; border-top: 2px solid var(--accent-blue); padding: 20px; z-index: 9999; text-align: center; color: #f8fafc; box-shadow: 0 -4px 15px rgba(0,0,0,0.5);">
    <div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 20px;">
        <p style="font-size: 0.85rem; margin: 0; text-align: left; flex: 1;">
            Utilizamos cookies propias y de terceros (incluidas cookies de redes de afiliados) para mejorar nuestros servicios, personalizar el contenido y analizar el tráfico. Al hacer clic en "Aceptar", consientes el uso de todas las cookies. <a href="#" style="color: var(--accent-blue); text-decoration: underline;">Política de Cookies</a>.
        </p>
        <div style="display: flex; gap: 10px; flex-shrink: 0;">
            <button id="accept-cookies" style="background: var(--accent-blue); color: white; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-family: inherit;">Aceptar</button>
            <button id="reject-cookies" style="background: transparent; color: #94a3b8; border: 1px solid #64748b; padding: 8px 20px; border-radius: 4px; cursor: pointer; font-family: inherit;">Rechazar</button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (!localStorage.getItem("cookies_accepted")) {
        document.getElementById("cookie-banner").style.display = "block";
    }
    
    document.getElementById("accept-cookies").addEventListener("click", function() {
        localStorage.setItem("cookies_accepted", "true");
        document.getElementById("cookie-banner").style.display = "none";
    });

    document.getElementById("reject-cookies").addEventListener("click", function() {
        localStorage.setItem("cookies_accepted", "false");
        document.getElementById("cookie-banner").style.display = "none";
    });
});
</script>

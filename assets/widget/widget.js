/**
 * Mteja Host Embed Loader (widget.js ~8KB)
 * Zero external dependencies · Mobile-native physics · Touch responsive
 */
(function() {
  'use strict';

  const scriptTag = document.getElementById('mteja-widget-loader') || document.currentScript;
  const embedUrl = scriptTag ? scriptTag.getAttribute('data-embed-url') : window.location.origin + '/mteja/widget-embed';
  const workspaceId = scriptTag ? scriptTag.getAttribute('data-workspace-id') : '';

  // Prevent multiple injections
  if (document.getElementById('mteja-widget-container')) return;

  // Create Container
  const container = document.createElement('div');
  container.id = 'mteja-widget-container';
  container.style.cssText = `
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 2147483647;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  `;

  // Create Floating Launcher Button (60px)
  const launcher = document.createElement('button');
  launcher.id = 'mteja-launcher-button';
  launcher.setAttribute('aria-label', 'Open support chat');
  launcher.style.cssText = `
    width: 56px;
    height: 56px;
    border-radius: 9999px;
    background: #111111;
    color: #ffffff;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1), background-color 150ms ease;
    touch-action: manipulation;
    outline: none;
  `;

  launcher.innerHTML = `
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" id="mteja-icon-chat">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
      <path d="M8 10h.01M12 10h.01M16 10h.01" stroke-width="2.8"></path>
    </svg>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" id="mteja-icon-close" style="display: none;">
      <line x1="18" y1="6" x2="6" y2="18"></line>
      <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>
  `;

  // Create Iframe Container
  const iframe = document.createElement('iframe');
  iframe.id = 'mteja-widget-iframe';
  iframe.src = embedUrl;
  iframe.style.cssText = `
    width: 380px;
    height: 580px;
    max-width: calc(100vw - 32px);
    max-height: calc(100vh - 100px);
    border: 1px solid #E5E5E2;
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
    margin-bottom: 16px;
    display: none;
    background: #FAF9F6;
    overflow: hidden;
  `;

  let isOpen = false;

  launcher.addEventListener('click', () => {
    isOpen = !isOpen;
    iframe.style.display = isOpen ? 'block' : 'none';
    const iconChat = document.getElementById('mteja-icon-chat');
    const iconClose = document.getElementById('mteja-icon-close');
    if (iconChat && iconClose) {
      iconChat.style.display = isOpen ? 'none' : 'block';
      iconClose.style.display = isOpen ? 'block' : 'none';
    }
    launcher.style.transform = 'scale(0.92)';
    setTimeout(() => { launcher.style.transform = 'scale(1)'; }, 160);
  });

  container.appendChild(iframe);
  container.appendChild(launcher);
  document.body.appendChild(container);

})();

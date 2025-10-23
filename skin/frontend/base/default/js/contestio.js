(function () {
    'use strict';
  
  if (window.__contestioMagento1ScriptActive) {
      console.log('contestio.js already initialised, skipping duplicate execution');
      return;
    }
    window.__contestioMagento1ScriptActive = true;

    console.log('contestio.js loaded');
  
  const logger = {
    log: function(message, data) {
      console.log('Contestio - ' + message, data ?? '');
    },
    warn: function(message, data) {
      console.warn('Contestio - ' + message, data ?? '');
    },
    error: function(message, data) {
      console.error('Contestio - ' + message, data ?? '');
    }
  };

  let iframeLoaded = false;
  let pendingNavigationActions = [];
  let navigationFlushTimeout = null;
  let awaitingAck = null;
  let awaitingAckTimeout = null;
  const ACK_TIMEOUT_MS = 2000;
  let lastAckPath = null;
  const allowedIframeOrigins = new Set();

  if (typeof window !== "undefined" && window.location && window.location.origin) {
    allowedIframeOrigins.add(window.location.origin);
  }

  window.__contestioAllowedOrigins = allowedIframeOrigins;

  function scheduleNavigationFlush(delay = 50) {
    if (navigationFlushTimeout) {
      return;
    }

    navigationFlushTimeout = setTimeout(() => {
      navigationFlushTimeout = null;
      if (iframeLoaded) {
        flushPendingNavigationActions();
      } else if (pendingNavigationActions.length) {
        scheduleNavigationFlush(Math.min(delay * 2, 500));
      }
    }, delay);
  }

  function normalizePathValue(pathname) {
    if (!pathname || pathname === '/' || pathname === '') {
      return '/';
    }

    return pathname.startsWith('/') ? pathname : '/' + pathname;
  }

  function normalizeOrigin(url) {
    if (!url || url === 'about:blank' || (typeof url === 'string' && url.startsWith('about:'))) {
      return null;
    }

    try {
      const resolvedUrl =
        typeof url === 'string' && (url.startsWith('http://') || url.startsWith('https://'))
          ? new URL(url)
          : new URL(url, window.location.origin);
      const origin = resolvedUrl.origin;
      if (origin && origin !== 'null') {
        return origin;
      }
    } catch (error) {
      logger.warn('contestio.js - unable to normalize origin from url', url, error);
    }
    return null;
  }

  function queueNavigationAction(action, options = {}) {
    if (
      !pendingNavigationActions.some(
        (item) =>
          item.action === action &&
          JSON.stringify(item.options) === JSON.stringify(options)
      )
    ) {
      pendingNavigationActions.push({ action, options });
      logger.log('contestio.js - queue navigation action', {
        action,
        options,
        queueLength: pendingNavigationActions.length,
      });
    }
    scheduleNavigationFlush();
  }

  function flushPendingNavigationActions() {
    if (!pendingNavigationActions.length) {
      logger.log('contestio.js - flush skip (queue empty)');
      return;
    }

    if (navigationFlushTimeout) {
      clearTimeout(navigationFlushTimeout);
      navigationFlushTimeout = null;
    }

    const actions = pendingNavigationActions.slice();
    pendingNavigationActions = [];

    actions.forEach(({ action, options }) => {
      logger.log('contestio.js - flush action', { action, options });
      sendNavigationUpdateToIframe(action, { ...options, force: true });
    });
  }

  function clearAwaitingAckTimeout() {
    if (awaitingAckTimeout) {
      clearTimeout(awaitingAckTimeout);
      awaitingAckTimeout = null;
    }
  }

  function setAwaitingAck(pathname, action, notifyAfterAck) {
    const normalized = normalizePathValue(pathname);
    awaitingAck = {
      path: normalized,
      action,
      notifyAfterAck: Boolean(notifyAfterAck),
    };

    // awaiting ack-path (verbose log removed)
    clearAwaitingAckTimeout();

    if (lastAckPath && lastAckPath === normalized) {
      completeAwaitingAck();
      return;
    }

    if (awaitingAck) {
      awaitingAckTimeout = setTimeout(() => {
        logger.warn('contestio.js - ack timeout, forcing completion', {
          awaitingAck,
        });
        const pending = awaitingAck;
        awaitingAck = null;
        awaitingAckTimeout = null;
        if (pending && pending.action) {
          sendNavigationUpdateToIframe(pending.action, {
            force: true,
            skipAckTracking: true,
          });
        }
      }, ACK_TIMEOUT_MS);
    }
  }

  function completeAwaitingAck() {
    if (!awaitingAck) {
      return;
    }

    const pending = awaitingAck;
    awaitingAck = null;
    clearAwaitingAckTimeout();

    // ack-path resolved (verbose log removed)

    if (pending.notifyAfterAck) {
      sendNavigationUpdateToIframe(pending.action || 'replace', {
        force: true,
        skipAckTracking: true,
      });
    }
  }

  function handleAckFromIframe(pathname) {
    const normalized = normalizePathValue(pathname);
    lastAckPath = normalized;
    // handle ack-path (verbose log removed)

    if (awaitingAck && awaitingAck.path === normalized) {
      completeAwaitingAck();
    }
  }

  class KeyboardManager {
    constructor(iframe) {
      this.iframe = iframe;
      this.lastHeight = window.visualViewport?.height || window.innerHeight;

      if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', this.handleViewportResize.bind(this));
      }
    }

    handleViewportResize(event) {
      const currentHeight = window.visualViewport.height;
      const heightDiff = Math.abs(this.lastHeight - currentHeight);

      // Scroll to the top if the height change
      if (heightDiff > 20 && currentHeight > this.lastHeight) {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      }

      this.lastHeight = currentHeight;
    }

  }

  function getContestioIframe() {
    return document.querySelector('.contestio-iframe');
  }

  function getIframeOrigin(iframe) {
    if (!iframe) return null;

    return normalizeOrigin(iframe.src);
  }

  function getParentPathname() {
    try {
      const urlParams = new URLSearchParams(window.location.search);
      const l = urlParams.get('l');

      if (!l || l === '/' || l === '') {
        return '/';
      }

      return l.startsWith('/') ? l : '/' + l;
    } catch (error) {
      logger.warn('contestio.js - unable to read parent pathname', error);
      return '/';
    }
  }

    function sendNavigationUpdateToIframe(action = 'replace', options = {}) {
    const {
      force = false,
      trackAck = false,
      notifyAfterAck = false,
      skipAckTracking = false,
    } = options;

    const iframe = getContestioIframe();

    if (!iframe || !iframe.contentWindow) {
      logger.warn('contestio.js - iframe not ready for navigation sync');
      queueNavigationAction(action, {
        trackAck: trackAck && !skipAckTracking,
        notifyAfterAck,
        skipAckTracking,
      });
      return;
    }

    if (!iframeLoaded && !force) {
      queueNavigationAction(action, {
        trackAck: trackAck && !skipAckTracking,
        notifyAfterAck,
        skipAckTracking,
      });
      return;
    }

    const iframeOrigin = getIframeOrigin(iframe);
    if (!iframeOrigin) return;
    allowedIframeOrigins.add(iframeOrigin);

      const pathname = getParentPathname();

      const message = {
        type: 'parent-navigation',
        action,
        pathname,
        title: document.title,
        timestamp: Date.now()
      };

      try {
      // send navigation update (verbose log removed)
      iframe.contentWindow.postMessage(message, iframeOrigin);
      iframeLoaded = true;
      iframe.dataset.contestioIframeLoaded = 'true';
      if (trackAck && !skipAckTracking) {
        setAwaitingAck(pathname, action, notifyAfterAck);
      }
    } catch (error) {
      logger.error('Error sending navigation update to iframe:', error);
      queueNavigationAction(action, {
        trackAck: trackAck && !skipAckTracking,
        notifyAfterAck,
        skipAckTracking,
      });
      scheduleNavigationFlush(100);
    }
  }

  function handleParentPopstate() {
    sendNavigationUpdateToIframe('popstate', {
      trackAck: true,
      notifyAfterAck: false,
    });
  }
  
    function init() {
      // Force the initialization if it's not already initialized
      if (window.contestioInitialized) {
        // Check if the necessary elements are present
        const container = document.querySelector('.contestio-container');
        const iframe = document.querySelector('.contestio-iframe');
        
        if (container && iframe) {
          // If the elements are present, force the initialization
          logger.log('contestio.js - reinitializing');
          window.contestioInitialized = false;
        } else {
          logger.log('contestio.js - skipping initialization (no elements found)');
          return;
        }
      }
      window.contestioInitialized = true;
  
      logger.log('contestio.js - init');
      const container = document.querySelector('.contestio-container');
      const iframe = document.querySelector('.contestio-iframe');
  
      if (!container || !iframe) {
      logger.warn('contestio.js - container or iframe not found');
      return;
    }

    if (iframe.dataset.contestioIframeLoaded === 'true') {
      iframeLoaded = true;
    }

    if (!iframe.dataset.contestioLoadListenerAttached) {
      iframe.addEventListener('load', () => {
        iframeLoaded = true;
        iframe.dataset.contestioIframeLoaded = 'true';
        logger.log('contestio.js - iframe load detected, flushing queue');
        flushPendingNavigationActions();
      });
      iframe.dataset.contestioLoadListenerAttached = 'true';
    }

    if (!iframeLoaded) {
      try {
        const href = iframe.contentWindow && iframe.contentWindow.location && iframe.contentWindow.location.href;
        if (href && href !== 'about:blank') {
          // Same-origin blank document, wait for remote load.
        }
      } catch (error) {
        iframeLoaded = true;
        iframe.dataset.contestioIframeLoaded = 'true';
        logger.log('contestio.js - iframe already loaded, flushing queue');
        flushPendingNavigationActions();
      }
    }

    // Initialize keyboard manager
    new KeyboardManager(iframe);
  
      function adjustHeight() {
        const mainContentElt = document.querySelector('.main-container');
        const containerElt = document.querySelector('.contestio-container');
  
        if (!mainContentElt || !containerElt) {
          logger.warn('contestio.js - mainContentElt or containerElt not found');
          return;
        }

        logger.log('contestio.js - adjusting height');
  
        let offset = mainContentElt.offsetTop || 0;
  
        const windowHeight = window.innerHeight;
        const newHeight = windowHeight - offset; // Remove the header/navbar height
        
        // Update the iframe height
        containerElt.style.height = `${newHeight}px`;
      }
  
      // Adjust the height immediately
      adjustHeight();
  
      // Adjust the height when the window is resized
      window.addEventListener('resize', adjustHeight);
  
      // Function to create and configure the message listener
      function createMessageListener() {
        const messageHandler = async (event) => {
          const iframeElt = document.querySelector('.contestio-iframe');
          // Strict security check
          const iframeOrigin = normalizeOrigin(iframeElt.src);
          const baseOrigin = normalizeOrigin(iframeElt?.dataset?.contestioBaseUrl);
          if (baseOrigin) {
            allowedIframeOrigins.add(baseOrigin);
          }
          if (iframeOrigin) {
            allowedIframeOrigins.add(iframeOrigin);
          }

          if (!event.origin) {
            return;
          }

          if (!allowedIframeOrigins.has(event.origin)) {
            logger.log('Message received from unauthorized origin:', event.origin, {
              allowed: Array.from(allowedIframeOrigins.values()),
            });
            return;
          }

          // Check that event.data exists and is an object
          if (!event.data || typeof event.data !== 'object' || !event.data.type) {
            return;
          }

          const replyOrigin =
            event.origin ||
            normalizeOrigin(iframeElt.src) ||
            normalizeOrigin(iframeElt.dataset?.contestioBaseUrl);
          if (!replyOrigin) {
            logger.warn('contestio.js - unable to determine reply origin', event);
            return;
          }

          const {
            type,
            loginCredentials,
            pathname,
            redirectUrl,
            clipboardText,
            cookie
          } = event.data;
  
          try {
            switch (type) {
              case 'login':
                // Url = current url without query params
                const url = window.location.href.includes('?') 
                  ? window.location.href.split('?')[0] 
                  : window.location.href;
                
                const loginUrl = url.endsWith('/') ? url + 'login' : url + '/login/post';
                logger.log('Attempting login to:', loginUrl);
  
                const response = await fetch(loginUrl, {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({
                    username: loginCredentials.username,
                    password: loginCredentials.password
                  }),
                });
  
                const data = await response.json();
  
                if (data.success) {
                  window.location.reload();
                } else {
                  event.source.postMessage({
                    loginResponse: {
                      success: false,
                      message: data.message,
                      data: data
                    }
                  }, replyOrigin);
                }
                break;
  
              case 'pathname':
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.delete('l');
                currentUrl.searchParams.delete('u');

                if (pathname !== '' && pathname !== '/') {
                  currentUrl.searchParams.set('l', pathname);
                } else {
                  currentUrl.searchParams.delete('l');
                }

              history.pushState(null, null, currentUrl.toString());
              sendNavigationUpdateToIframe('push');
              break;
                
              case 'history-push':
        const pushUrl = new URL(window.location.href);
        pushUrl.searchParams.delete('l');
        pushUrl.searchParams.delete('u');

        if (pathname !== '' && pathname !== '/') {
          pushUrl.searchParams.set('l', pathname);
        } else {
          pushUrl.searchParams.delete('l');
        }

        const newPushUrl = pushUrl.toString();

        if (window.location.href !== newPushUrl) {
          history.pushState({ title: event.data.title }, event.data.title || '', newPushUrl);
          if (event.data.title) {
            document.title = event.data.title;
          }
          sendNavigationUpdateToIframe('push');
        }
        break;

      case 'history-replace':
        const replaceUrl = new URL(window.location.href);
        replaceUrl.searchParams.delete('l');
        replaceUrl.searchParams.delete('u');

        if (pathname !== '' && pathname !== '/') {
          replaceUrl.searchParams.set('l', pathname);
        } else {
          replaceUrl.searchParams.delete('l');
        }

        const newReplaceUrl = replaceUrl.toString();
        if (window.location.href !== newReplaceUrl) {
          history.replaceState({ title: event.data.title }, event.data.title || '', newReplaceUrl);
          if (event.data.title) {
            document.title = event.data.title;
          }
          sendNavigationUpdateToIframe('replace');
        }
        break;

              case 'history-back':
                logger.log('History back');
                if (window.history.length > 1) {
                  history.back();
                }
                break;

              case 'request-parent-path':
                logger.log('Iframe requested parent path sync');
                sendNavigationUpdateToIframe('sync', {
                  trackAck: true,
                  notifyAfterAck: false,
                });
                break;

              case 'ack-path':
                {
                  const ackPath = pathname || event.data.fullPath || '/';
              // ack-path message received (verbose log removed)
                  handleAckFromIframe(ackPath);
                }
                break;

              case 'redirect':
                logger.log('Redirect to:', redirectUrl);
                window.location.href = redirectUrl;
                break;
  
              case 'clipboard':
                logger.log('Copy to clipboard:', clipboardText);
                await navigator.clipboard.writeText(clipboardText);
                break;
  
              case 'createCookie':
                // Create cookie
                document.cookie = `${cookie.name}=${cookie.value}; expires=${cookie.expires}; path=${cookie.path}`;
                logger.log('Create cookie:', cookie, `${cookie.name}=${cookie.value}; expires=${cookie.expires}; path=${cookie.path}`, document.cookie);
                break;
  
              case 'getCookie':
                // Get cookie
                const cookieValue = document.cookie.split('; ').find(row => row.startsWith(`${cookie.name}=`))?.split('=')[1];
                logger.log('Get cookie:', cookie, cookieValue);
                // Send response to the iframe
                event.source.postMessage({
                  getCookieResponse: {
                    success: cookieValue ? true : false,
                    cookieName: cookie.name,
                    cookieValue: cookieValue
                  }
                }, replyOrigin);
                break;
  
              case 'deleteCookie':
                // Delete cookie
                document.cookie = `${cookie.name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
                logger.log('Delete cookie:', cookie, document.cookie);
                break;
  
              default:
                logger.warn('Unknown message type:', type);
            }
          } catch (error) {
            logger.error('Error processing message:', error);
            
            if (type === 'login') {
              event.source.postMessage({
                loginResponse: {
                  success: false,
                  message: "Erreur lors du traitement de la requête",
                  error: error.message
                }
              }, replyOrigin);
            }
          }
        };

        // Add the listener
        window.addEventListener('message', messageHandler);

        // Return a cleanup function
        return () => {
          logger.log('Cleaning up message listener');
          window.removeEventListener('message', messageHandler);
          window.__contestioMessageListenerActive = false;
        };
      }

      // Handle the listener lifecycle
      let cleanup = window.__contestioMessageListenerCleanup || null;
      window.__contestioMessageListenerActive = window.__contestioMessageListenerActive || false;

      function setupListener() {
        if (window.__contestioMessageListenerActive) {
          logger.log('contestio.js - message listener already active, skipping setup');
          return;
        }
        logger.log('Setting up message listener');
        // Clean up old listener if it exists
        if (cleanup) {
          cleanup();
        }
        // Create a new listener
        cleanup = createMessageListener();
        window.__contestioMessageListenerCleanup = cleanup;
        window.__contestioMessageListenerActive = true;
      }

      // Set up the listener initially
      setupListener();

      // Reconfigure the listener when the page becomes visible
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
          logger.log('Visibility changed to visible, reconfiguring listener');
          setupListener();
        }
      });

      sendNavigationUpdateToIframe('init', {
        trackAck: true,
        notifyAfterAck: false,
      });
    }

    // Modify the initialization part to also listen to navigation changes
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  
    // Synchronize iframe navigation when browser history changes
    window.addEventListener('popstate', handleParentPopstate);
  })();
  

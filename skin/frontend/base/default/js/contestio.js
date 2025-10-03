(function () {
    'use strict';
  
    console.log('contestio.js loaded');
  
    const verbose = false;
    
    const logger = {
      log: function(message, data) {
        if (verbose) {
          console.log('Contestio - ' + message, data ?? '');
        }
      },
      warn: function(message, data) {
        if (verbose) {
          console.warn('Contestio - ' + message, data ?? '');
        }
      },
      error: function(message, data) {
        if (verbose) {
          console.error('Contestio - ' + message, data ?? '');
        }
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

      try {
        return new URL(iframe.src).origin;
      } catch (error) {
        logger.warn('contestio.js - unable to determine iframe origin', error);
        return null;
      }
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

    function sendNavigationUpdateToIframe(action = 'replace') {
      const iframe = getContestioIframe();

      if (!iframe || !iframe.contentWindow) {
        logger.warn('contestio.js - iframe not ready for navigation sync');
        return;
      }

      const iframeOrigin = getIframeOrigin(iframe);
      if (!iframeOrigin) return;

      const pathname = getParentPathname();

      const message = {
        type: 'parent-navigation',
        action,
        pathname,
        title: document.title,
        timestamp: Date.now()
      };

      try {
        logger.log('Sending navigation sync to iframe:', message);
        iframe.contentWindow.postMessage(message, iframeOrigin);
      } catch (error) {
        logger.error('Error sending navigation update to iframe:', error);
      }
    }

    function handleParentPopstate() {
      logger.log('contestio.js - popstate detected');
      sendNavigationUpdateToIframe('popstate');
      init();
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
          const iframeOrigin = new URL(iframeElt.src).origin;
          if (!event.origin || event.origin !== iframeOrigin) {
            logger.warn('Message received from unauthorized origin:', event.origin);
            return;
          }
  
          // Check that event.data exists and is an object
          if (!event.data || typeof event.data !== 'object') {
            logger.warn('Invalid message received:', event.data);
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
                  }, iframeOrigin);
                }
                break;
  
              case 'pathname':
                const currentUrl = new URL(window.location.href);
                currentUrl.search = '';
                currentUrl.searchParams.delete('l');
                currentUrl.searchParams.delete('u');
  
                let newUrl = currentUrl.toString();
                if (pathname !== '' && pathname !== '/') {
                  newUrl += (newUrl.includes('?') ? '&' : '?') + 'l=' + pathname;
                }
  
              logger.log('Update URL to:', newUrl);
              history.pushState(null, null, newUrl);
              sendNavigationUpdateToIframe('push');
              break;
                
              case 'history-push':
                const pushUrl = new URL(window.location.href);
                pushUrl.search = '';
                pushUrl.searchParams.delete('l');
                pushUrl.searchParams.delete('u');

                let newPushUrl = pushUrl.toString();
                if (pathname !== '' && pathname !== '/') {
                  newPushUrl += (newPushUrl.includes('?') ? '&' : '?') + 'l=' + pathname;
                }

              logger.log('History push to:', newPushUrl);
              history.pushState({ title: event.data.title }, event.data.title || '', newPushUrl);
              if (event.data.title) {
                document.title = event.data.title;
              }
              sendNavigationUpdateToIframe('push');
              break;

              case 'history-replace':
                const replaceUrl = new URL(window.location.href);
                replaceUrl.search = '';
                replaceUrl.searchParams.delete('l');
                replaceUrl.searchParams.delete('u');

                let newReplaceUrl = replaceUrl.toString();
                if (pathname !== '' && pathname !== '/') {
                  newReplaceUrl += (newReplaceUrl.includes('?') ? '&' : '?') + 'l=' + pathname;
                }

              logger.log('History replace to:', newReplaceUrl);
              history.replaceState({ title: event.data.title }, event.data.title || '', newReplaceUrl);
              if (event.data.title) {
                document.title = event.data.title;
              }
              sendNavigationUpdateToIframe('replace');
              break;

              case 'history-back':
                logger.log('History back');
                if (window.history.length > 1) {
                  history.back();
                }
                break;

              case 'request-parent-path':
                logger.log('Iframe requested parent path sync');
                if (!event.source || typeof event.source.postMessage !== 'function') {
                  break;
                }

                event.source.postMessage({
                  type: 'parent-navigation',
                  action: 'sync',
                  pathname: getParentPathname(),
                  title: document.title,
                  timestamp: Date.now()
                }, iframeOrigin);
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
                }, iframeOrigin);
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
              }, iframeOrigin);
            }
          }
        };
  
        // Add the listener
        window.addEventListener('message', messageHandler);
  
        // Return a cleanup function
        return () => {
          logger.log('Cleaning up message listener');
          window.removeEventListener('message', messageHandler);
        };
      }
  
      // Handle the listener lifecycle
      let cleanup = null;
  
      function setupListener() {
        logger.log('Setting up message listener');
        // Clean up old listener if it exists
        if (cleanup) {
          cleanup();
        }
        // Create a new listener
        cleanup = createMessageListener();
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

      sendNavigationUpdateToIframe('init');
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
  

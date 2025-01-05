// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import { createRoot } from "react-dom/client";
import React from "react";
import { AppStateInterface } from "../interface/AppStateInterface";
import { SyncWebsiteStateEnum } from "../enum/SyncWebsiteStateEnum";
import { getTranslations } from "../functions/translations";

const stringApiHostUrl = $("[data-sage-api-host-url]").attr(
  "data-sage-api-host-url",
);
const stringAuthorization = $("[data-sage-authorization]").attr(
  "data-sage-authorization",
);
const containerSelector = "#sage_tasks";

let translations: any = getTranslations();

const AppStateComponent = () => {
  const [appState, setAppState] = React.useState<AppStateInterface | null>(
    null,
  );

  const setIntervalAndExecute = (fn: Function, t: number) => {
    fn();
    return setInterval(fn, t);
  };

  const createWebsocket = () => {
    let apiHostUrl: URL = null;
    const pingTime = 5000;
    let lastMessageTime: number = null;
    if (stringApiHostUrl && stringAuthorization) {
      try {
        apiHostUrl = new URL(stringApiHostUrl);
      } catch (e) {
        apiHostUrl = null;
        console.error(e);
        return;
      }
      let hasError = false;
      const url =
        "wss://" + apiHostUrl.host + "/ws?authorization=" + stringAuthorization;
      const ws = new WebSocket(url);
      let intervalPing: number | null = null;
      let alreadyClose = false;
      let nbLost = 0;

      const wsClose = () => {
        if (alreadyClose) {
          return;
        }
        alreadyClose = true;
        if (intervalPing !== null) {
          clearInterval(intervalPing);
        }
        setTimeout(
          () => {
            createWebsocket();
          },
          hasError ? 5000 : 1000,
        );
      };

      ws.onopen = () => {
        console.log(`ws.onopen`);
        ws.send(
          JSON.stringify({
            Get: "appState",
          }),
        );
        intervalPing = setIntervalAndExecute(() => {
          ws.send(
            JSON.stringify({
              Get: "ping",
            }),
          );
          const waitPingReturn = 1000;
          setTimeout(() => {
            if (
              lastMessageTime === null ||
              Date.now() - waitPingReturn > lastMessageTime
            ) {
              nbLost++;
              console.log("todo connection lost"); // todo connection lost, display a message on screen
              if (nbLost > 3) {
                try {
                  wsClose();
                  ws.close();
                } catch (e) {
                  console.error(e);
                }
              }
            }
          }, waitPingReturn);
        }, pingTime);
      };

      ws.onmessage = (message) => {
        // region ping management
        lastMessageTime = Date.now();
        nbLost = 0;
        // endregion
        const data = JSON.parse(message.data);
        if (data.hasOwnProperty("AppState")) {
          setAppState(data.AppState);
          console.log(data.AppState);
        }
      };

      ws.onerror = (evt) => {
        console.log("ws.onerror", evt);
        hasError = true;
      };

      ws.onclose = (evt) => {
        console.log("ws.onclose alreadyClose: " + alreadyClose, evt);
        wsClose();
      };
    }
  };

  React.useEffect(() => {
    createWebsocket();
  }, []);

  React.useEffect(() => {
    if (appState && appState.SyncWebsite !== null) {
      $(containerSelector).removeClass("hidden");
    }
  }, [appState]);

  return (
    <div>
      {appState?.SyncWebsite && (
        <>
          <p className="h5">
            {translations.enum.syncWebsiteState[appState.SyncWebsite.State]}
          </p>
          {appState.SyncWebsite.State === SyncWebsiteStateEnum.CreateTasks ? (
            <>
              <p>{appState.SyncWebsite.WebsiteId}</p>
              <p>{appState.SyncWebsite.NbTasksToDo}</p>
            </>
          ) : (
            <></>
          )}
        </>
      )}
    </div>
  );
};

// Render your React component instead
const root = createRoot(
  document.querySelector(containerSelector + " .content"),
);
root.render(<AppStateComponent />);

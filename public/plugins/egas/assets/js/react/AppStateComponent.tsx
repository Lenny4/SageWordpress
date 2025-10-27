// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import {createRoot} from "react-dom/client";
import React from "react";
import {
  AppStateInterface,
  SyncWebsiteJobInterface,
  TaskJobSyncWebsiteJobInterface,
} from "../interface/AppStateInterface";
import {getTranslations} from "../functions/translations";
import {LinearProgress} from "@mui/material";
import {LinearProgressWithLabel} from "./component/LinearProgressWithLabel";
import {getDiff} from "json-difference";
import {TOKEN} from "../token";

declare const SOCKET_PORT: number | undefined;
const siteUrl = $(`[data-${TOKEN}-site-url]`).attr(`data-${TOKEN}-site-url`);
const wpnonce = $(`[data-${TOKEN}-nonce]`).attr(`data-${TOKEN}-nonce`);

const humanizeDuration = require("humanize-duration");

const stringApiHostUrl = $(`[data-${TOKEN}-api-host-url]`).attr(
  `data-${TOKEN}-api-host-url`,
);
const stringAuthorization = $(`[data-${TOKEN}-authorization]`).attr(
  `data-${TOKEN}-authorization`,
);
const language = $(`[data-${TOKEN}-language]`).attr(`data-${TOKEN}-language`);

const containerSelector = `#${TOKEN}_appstate`;
const joinApiContainerSelector = `#${TOKEN}_join_api`;

let translations: any = getTranslations();

interface State {
  SyncWebsiteJob: SyncWebsiteJobInterface;
}

interface State2 {
  TaskJobSyncWebsiteJob: TaskJobSyncWebsiteJobInterface;
}

const TaskJobSyncWebsiteJobComponent: React.FC<State2> = React.memo(
  ({TaskJobSyncWebsiteJob}) => {
    return (
      <>
        <p>
          <span>
            {translations.enum.taskJobType[TaskJobSyncWebsiteJob.TaskJobType]}
          </span>
          {TaskJobSyncWebsiteJob.TaskJobDoneSpeed !== null && (
            <>
              <br/>
              <span>
                {translations.words.taskJobDoneSpeed + ": "}
                {humanizeDuration(TaskJobSyncWebsiteJob.TaskJobDoneSpeed, {
                  language: language,
                })}
              </span>
            </>
          )}
          {TaskJobSyncWebsiteJob.RemainingTime !== null && (
            <>
              <br/>
              <span>
                {translations.words.remainingTime + ": "}
                {humanizeDuration(TaskJobSyncWebsiteJob.RemainingTime, {
                  language: language,
                  round: true,
                })}
              </span>
            </>
          )}
        </p>
        <div>
          {TaskJobSyncWebsiteJob.NewNbTasks === null ? (
            <LinearProgress/>
          ) : (
            <LinearProgressWithLabel
              done={TaskJobSyncWebsiteJob.NbTaskDone}
              max={TaskJobSyncWebsiteJob.NewNbTasks}
            />
          )}
        </div>
      </>
    );
  },
  (oldProps, newProps) => {
    const diff = getDiff(oldProps ?? {}, newProps ?? {});
    return (
      diff.added.length === 0 &&
      diff.edited.length === 0 &&
      diff.removed.length === 0
    );
  },
);

const SyncWebsiteJobComponent: React.FC<State> = React.memo(
  ({SyncWebsiteJob}) => {
    return (
      <>
        {SyncWebsiteJob.Show && (
          <div>
            <p>
              <span className="h5">
                {translations.enum.syncWebsiteState[SyncWebsiteJob.State]}
              </span>
              <br/>
              <span>
                {translations.sentences.nbThreads}: {SyncWebsiteJob.NbThreads}
              </span>
            </p>
            <ol>
              {SyncWebsiteJob.TaskJobSyncWebsiteJobs.map(
                (taskJobSyncWebsiteJob, indexTaskJobSyncWebsiteJob) => (
                  <li key={indexTaskJobSyncWebsiteJob}>
                    <TaskJobSyncWebsiteJobComponent
                      TaskJobSyncWebsiteJob={taskJobSyncWebsiteJob}
                    />
                  </li>
                ),
              )}
            </ol>
          </div>
        )}
      </>
    );
  },
  (oldProps, newProps) => {
    const diff = getDiff(oldProps ?? {}, newProps ?? {});
    return (
      diff.added.length === 0 &&
      diff.edited.length === 0 &&
      diff.removed.length === 0
    );
  },
);

const AppStateComponent = () => {
  const [appState, setAppState] = React.useState<AppStateInterface | null>(
    null,
  );
  const [errorWebsocket, setErrorWebsocket] = React.useState<string | null>(
    null,
  );
  const [errorSolveAuthorizationError, setErrorSolveAuthorizationError] =
    React.useState<string | null>(null);
  const [hasErrorWebsocketAuthorization, setHasErrorWebsocketAuthorization] =
    React.useState<boolean>(false);
  const [loadingAuthorizationError, setLoadingAuthorizationError] =
    React.useState<boolean>(false);

  const setIntervalAndExecute = (fn: Function, t: number) => {
    fn();
    return setInterval(fn, t);
  };

  const createWebsocket = () => {
    let apiHostUrl: URL = null;
    const pingTime = 5000;
    let lastMessageTime: number = null;
    let copyAppStateWs = appState;
    if (stringApiHostUrl && stringAuthorization) {
      try {
        apiHostUrl = new URL(stringApiHostUrl);
      } catch (e) {
        apiHostUrl = null;
        console.error(e);
        return;
      }
      let hasError = false;
      let ignoreErrorTimeout = false;
      let socketPort = '';
      if (SOCKET_PORT) {
        socketPort = ':' + SOCKET_PORT;
      }
      const url =
        "wss://" + apiHostUrl.host + socketPort + "/ws?authorization=" + stringAuthorization;
      const ws = new WebSocket(url);
      const connectionTimeout = setTimeout(() => {
        if (ws.readyState !== WebSocket.OPEN) {
          ignoreErrorTimeout = true;
          ws.close();
        }
      }, 10_000);
      let intervalPing: number | null = null;
      let alreadyClose = false;
      let nbLost = 0;

      const wsReconnect = () => {
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
          ignoreErrorTimeout ? 0 : hasError ? 5000 : 1000,
        );
      };

      ws.onopen = () => {
        console.log(`ws.onopen`);
        $(joinApiContainerSelector).addClass("hidden");
        clearTimeout(connectionTimeout);
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
          const waitPingReturn = pingTime - 1000;
          if (pingTime < waitPingReturn) {
            throw "pingTime < waitPingReturn";
          }
          setTimeout(() => {
            if (
              lastMessageTime === null ||
              lastMessageTime < Date.now() - waitPingReturn
            ) {
              nbLost++;
              if (nbLost > 3) {
                try {
                  ws.close();
                  wsReconnect();
                } catch (e) {
                  console.error(e);
                }
              }
            } else {
              // ping worked
              if ($(containerSelector).hasClass("notice-error")) {
                $(containerSelector).addClass("hidden");
                setErrorWebsocket(null);
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
        if (data.Get === "appState") {
          const diff = getDiff(copyAppStateWs ?? {}, data.AppState);
          if (
            diff.added.length !== 0 ||
            diff.edited.length !== 0 ||
            diff.removed.length !== 0
          ) {
            console.log(data.AppState);
            copyAppStateWs = data.AppState;
            setAppState(data.AppState);
          }
        }
      };

      ws.onerror = (evt) => {
        console.log("ws.onerror", evt);
        clearTimeout(connectionTimeout);
        hasError = true;
      };

      ws.onclose = (evt) => {
        console.log("ws.onclose alreadyClose: " + alreadyClose, evt);
        clearTimeout(connectionTimeout);
        $(containerSelector).removeClass("hidden");
        $(containerSelector).removeClass("notice-info");
        $(containerSelector).addClass("notice-error");
        setErrorWebsocket(evt.reason);
        setHasErrorWebsocketAuthorization(evt.code === 1008);
        wsReconnect();
      };
    }
  };

  const solveAuthorizationError = async () => {
    if (loadingAuthorizationError) {
      return;
    }
    setErrorSolveAuthorizationError(null);
    setLoadingAuthorizationError(true);
    const response = await fetch(
      siteUrl +
      "/index.php?rest_route=" +
      encodeURIComponent(`/${TOKEN}/v1/add-website-sage-api`) +
      "&_wpnonce=" +
      wpnonce,
    );
    if (response.ok) {
      window.location.reload();
    } else {
      setLoadingAuthorizationError(false);
      let data: any = await response.json();
      try {
        data = JSON.stringify(JSON.parse(data), undefined, 2);
      } catch (e) {
        // nothing
      }
      setErrorSolveAuthorizationError(data.toString());
    }
  };

  React.useEffect(() => {
    createWebsocket();
  }, []);

  React.useEffect(() => {
    if (appState) {
      if (appState.SyncWebsiteJob !== null) {
        $(containerSelector).removeClass("notice-error");
        $(containerSelector).addClass("notice-info");
        $(containerSelector).removeClass("hidden");
      } else {
        $(containerSelector).addClass("hidden");
      }
    }
  }, [appState]);

  return (
    <div>
      {errorWebsocket !== null ? (
        <div>
          <p>
            {translations.sentences.errorWebsocket}
            {errorWebsocket !== "" && (
              <>
                {":"}
                <code>{errorWebsocket}</code>
              </>
            )}
          </p>
          {errorSolveAuthorizationError !== "" && (
            <pre>{errorSolveAuthorizationError}</pre>
          )}
          {hasErrorWebsocketAuthorization && (
            <>
              <br/>
              <button
                className="button-primary"
                disabled={loadingAuthorizationError}
                onClick={solveAuthorizationError}
              >
                {translations.words.fixTheProblem}
                {loadingAuthorizationError && (
                  <span className="spinner is-active"></span>
                )}
              </button>
            </>
          )}
        </div>
      ) : (
        appState?.SyncWebsiteJob && (
          <>
            <SyncWebsiteJobComponent SyncWebsiteJob={appState.SyncWebsiteJob}/>
          </>
        )
      )}
    </div>
  );
};

// Render your React component instead
const root = createRoot(
  document.querySelector(containerSelector + " .content"),
);
root.render(<AppStateComponent/>);

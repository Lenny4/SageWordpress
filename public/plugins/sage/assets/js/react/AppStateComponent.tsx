// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import { createRoot } from "react-dom/client";
import React from "react";
import {
  AppStateInterface,
  SyncWebsiteJobInterface,
  TaskJobSyncWebsiteJobInterface,
} from "../interface/AppStateInterface";
import { getTranslations } from "../functions/translations";
import { LinearProgress } from "@mui/material";
import { LinearProgressWithLabel } from "./component/LinearProgressWithLabel";
import { getDiff } from "json-difference";

const humanizeDuration = require("humanize-duration");

const stringApiHostUrl = $("[data-sage-api-host-url]").attr(
  "data-sage-api-host-url",
);
const stringAuthorization = $("[data-sage-authorization]").attr(
  "data-sage-authorization",
);
const language = $("[data-sage-language]").attr("data-sage-language");

const containerSelector = "#sage_tasks";

let translations: any = getTranslations();

interface State {
  SyncWebsiteJob: SyncWebsiteJobInterface;
}

interface State2 {
  TaskJobSyncWebsiteJob: TaskJobSyncWebsiteJobInterface;
}

const TaskJobSyncWebsiteJobComponent: React.FC<State2> = React.memo(
  ({ TaskJobSyncWebsiteJob }) => {
    return (
      <>
        <p>
          <span>
            {translations.enum.taskJobType[TaskJobSyncWebsiteJob.TaskJobType]}
          </span>
          {TaskJobSyncWebsiteJob.TaskJobDoneSpeed !== null && (
            <>
              <br />
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
              <br />
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
            <LinearProgress />
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
  ({ SyncWebsiteJob }) => {
    return (
      <>
        {SyncWebsiteJob.Show && (
          <div>
            <p>
              <span className="h5">
                {translations.enum.syncWebsiteState[SyncWebsiteJob.State]}
              </span>
              <br />
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
      const url =
        "wss://" + apiHostUrl.host + "/ws?authorization=" + stringAuthorization;
      const ws = new WebSocket(url);
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
                  wsReconnect();
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
        hasError = true;
      };

      ws.onclose = (evt) => {
        console.log("ws.onclose alreadyClose: " + alreadyClose, evt);
        wsReconnect();
      };
    }
  };

  React.useEffect(() => {
    createWebsocket();
  }, []);

  React.useEffect(() => {
    if (appState && appState.SyncWebsiteJob !== null) {
      $(containerSelector).removeClass("hidden");
    }
  }, [appState]);

  return (
    <div>
      {appState?.SyncWebsiteJob && (
        <>
          <SyncWebsiteJobComponent SyncWebsiteJob={appState.SyncWebsiteJob} />
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

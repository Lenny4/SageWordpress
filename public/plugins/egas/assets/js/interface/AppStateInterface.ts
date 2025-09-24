import {SyncWebsiteStateEnum} from "../enum/SyncWebsiteStateEnum";
import {TaskJobTypeEnum} from "../enum/TaskJobTypeEnum";

export interface AppStateInterface {
  SyncWebsiteJob: SyncWebsiteJobInterface;
}

export interface SyncWebsiteJobInterface {
  WebsiteId: number;
  Show: boolean;
  NbThreads: number;
  State: SyncWebsiteStateEnum;
  TaskJobSyncWebsiteJobs: TaskJobSyncWebsiteJobInterface[] | null;
}

export interface TaskJobSyncWebsiteJobInterface {
  NbTaskDone: number;
  NewNbTasks: number | null;
  TaskJobType: TaskJobTypeEnum;
  TaskJobDoneSpeed: number | null;
  RemainingTime: number | null;
}

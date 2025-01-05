import { SyncWebsiteStateEnum } from "../enum/SyncWebsiteStateEnum";
import { TaskJobTypeEnum } from "../enum/TaskJobTypeEnum";

export interface AppStateInterface {
  SyncWebsiteJob: SyncWebsiteJobInterface;
}

export interface SyncWebsiteJobInterface {
  WebsiteId: number;
  Show: boolean;
  State: SyncWebsiteStateEnum;
  TaskJobSyncWebsiteJobs: TaskJobSyncWebsiteJobInterface[] | null;
}

export interface TaskJobSyncWebsiteJobInterface {
  NbTaskDone: number;
  NewNbTasks: number | null;
  TaskJobType: TaskJobTypeEnum;
}

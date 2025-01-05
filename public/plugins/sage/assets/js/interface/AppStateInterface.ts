import { SyncWebsiteStateEnum } from "../enum/SyncWebsiteStateEnum";

export interface AppStateInterface {
  SyncWebsite: SyncWebsiteInterface;
}

export interface SyncWebsiteInterface {
  NbTasksToDo: number;
  State: SyncWebsiteStateEnum;
  WebsiteId: number;
}

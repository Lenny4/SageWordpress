export interface AppStateInterface {
  SyncWebsite: SyncWebsiteInterface;
}

export interface SyncWebsiteInterface {
  NbTasksToDo: number;
  State: number;
  WebsiteId: number;
}

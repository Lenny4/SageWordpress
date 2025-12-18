import { HTMLInputTypeAttribute } from "react";
import { TabInterface } from "./TabInterface";
import { GridProps } from "@mui/material/Grid/Grid";
import { TabsProps } from "@mui/material/Tabs/Tabs";
import { ResultTableInterface } from "../react/component/list/ListSageEntityComponent";

export interface FormInterface {
  content: FormContentInterface;
}

export interface ResponseTableLineItemInterface {
  items: TableLineItemInterface[] | null;
  response: ResultTableInterface | null;
}

export interface TableLineItemInterface {
  item: any;
  identifier: string;
  lines: TableLineInterface[];
}

export interface TableLineInterface {
  Dom?: any;
  field?: FieldInterface;
}

export interface TableInterface {
  headers: string[];
  items:
    | TableLineItemInterface[]
    | ((
        search: string,
        page?: number,
        cacheResponse?: ResultTableInterface,
      ) => Promise<ResponseTableLineItemInterface>);
  fullWidth?: boolean;
  localStorageItemName?: string;
  add?: TableAddInterface;
  removeItem?: Function;
  search?: Function;
  addItem?: Function;
}

export interface TableAddInterface {
  table: TableInterface;
}

export interface FormTabInterface {
  tabProps?: TabsProps;
  tabs: TabInterface[];
  id: string; // usefull for onSubmitForm to choose which tab to select
  ref?: any;
}

export interface FormContentInterface {
  Container?: any;
  props?: GridProps;
  Dom?: any;
  fields?: FieldInterface[];
  children?: FormContentInterface[];
  table?: TableInterface;
  tabs?: FormTabInterface; // todo change name to formTab
}

export interface TabFormValidInterface {
  tabRef: any;
  index: number;
}

export interface FormValidInterface {
  valid: any;
  details: FormErrorDetailInterface[];
}

export interface FormErrorDetailInterface {
  ref: any;
  dRef: any;
  valid: boolean;
  tabs?: TabFormValidInterface[];
}

export interface ErrorMessageInterface {
  fieldName: string;
  message: string;
}

export interface InputInterface<
  F extends (arg: any) => any = (arg: any) => any,
> {
  value: any;
  error?: string | null;
  readOnly?: boolean;
  validator?: FieldValidatorInterface<F>;
}

export interface FieldValidatorInterface<F extends (arg: any) => any> {
  functionName: F;
  params?: Parameters<F>[0]; // Extracts the shape of the single object parameter
}

export interface FieldInterface<
  F extends (arg: any) => any = (arg: any) => any,
> {
  label?: string;
  name: string;
  DomField: any;
  readOnly?: boolean;
  autoUppercase?: boolean;
  cannotBeChangeOnWebsite?: boolean;
  tooltip?: string;
  hideLabel?: boolean;
  triggerFormContentChanged?: TriggerFormContentChanged;
  options?: FormInputOptions[];
  type?: HTMLInputTypeAttribute | undefined;
  errorMessage?: string;
  initValues: InputInterface;
  ref?: any;
}

export interface TriggerFormContentChanged {
  (name: string, newValue: string): void;
}

export type FormInputOptions = {
  label: string;
  value: string;
  disabled?: boolean;
};

export interface OptionChangeInputInterface {
  autoUppercase?: boolean;
}

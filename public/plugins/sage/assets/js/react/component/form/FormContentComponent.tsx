import * as React from "react";
import { Dispatch, SetStateAction } from "react";
import {
  FormContentInterface,
  FormInterface,
} from "../../../interface/InputInterface";
import { Grid } from "@mui/material";
import { FormTableComponent } from "./FormTableComponent";
import { FormFieldComponent } from "./FormFieldComponent";

const defaultContainer = Grid;
const defaultProps = {
  size: { xs: 12, md: 6 },
};

type State = {
  content: FormContentInterface[];
  values: any;
  transPrefix: string;
  getForm?: Function;
  setForm?: Dispatch<SetStateAction<FormInterface>>;
  handleChange: (
    prop: keyof any,
  ) => (event: React.ChangeEvent<HTMLInputElement>) => void;
  handleChangeSelect: (
    prop: keyof any,
  ) => (event: React.ChangeEvent<HTMLSelectElement>) => void;
};

export const FormContentComponent: React.FC<State> = ({
  content,
  values,
  transPrefix,
  handleChange,
  handleChangeSelect,
  getForm,
  setForm,
}) => (
  <>
    {content.map(
      ({ Container, props, Dom, fields, children, table }, indexContainer) => {
        Container = Container ?? defaultContainer;
        props = props ?? defaultProps;

        return (
          <Container {...props} key={indexContainer}>
            {Dom}
            {fields?.map((field, indexField) => (
              <FormFieldComponent
                key={indexField}
                field={field}
                values={values}
                transPrefix={transPrefix}
                handleChange={handleChange}
                handleChangeSelect={handleChangeSelect}
              />
            ))}
            {table && (
              <FormTableComponent
                table={table}
                values={values}
                transPrefix={transPrefix}
                handleChange={handleChange}
                handleChangeSelect={handleChangeSelect}
                getForm={getForm}
                setForm={setForm}
              />
            )}
            {children && children.length > 0 && (
              <FormContentComponent
                content={children}
                values={values}
                handleChange={handleChange}
                handleChangeSelect={handleChangeSelect}
                transPrefix={transPrefix}
              />
            )}
          </Container>
        );
      },
    )}
  </>
);

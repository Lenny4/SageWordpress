import * as React from "react";
import { TableInterface } from "../../../interface/InputInterface";
import { FormFieldComponent } from "./FormFieldComponent";

type State = {
  table: TableInterface;
  values: any;
  transPrefix: string | undefined;
  handleChange: (
    prop: keyof any,
  ) => (event: React.ChangeEvent<HTMLInputElement>) => void | undefined;
  handleChangeSelect: (
    prop: keyof any,
  ) => (event: React.ChangeEvent<HTMLSelectElement>) => void | undefined;
};

export const FormTableComponent: React.FC<State> = ({
  table,
  transPrefix,
  values,
  handleChange,
  handleChangeSelect,
}) => {
  return (
    <table>
      <thead>
        <tr>
          {table.headers.map((header, index) => (
            <th key={index}>{header}</th>
          ))}
        </tr>
      </thead>
      <tbody>
        {table.lines.map((line, index) => (
          <tr key={index}>
            {line.map((cell, indexCell) => {
              const Dom = cell.Dom;
              return (
                <td key={indexCell}>
                  {Dom}
                  {cell.field && (
                    <FormFieldComponent
                      key={indexCell}
                      field={cell.field}
                      values={values}
                      transPrefix={transPrefix}
                      handleChange={handleChange}
                      handleChangeSelect={handleChangeSelect}
                    />
                  )}
                </td>
              );
            })}
          </tr>
        ))}
      </tbody>
    </table>
  );
};
